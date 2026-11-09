<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\MessageDispatcher;
use App\Services\OutboundMailService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class CommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->seed(SettingSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));
        Http::preventStrayRequests();
    }

    private function sms(): void
    {
        Setting::set('sms_enabled', true, 'sms');
        Setting::set('sms_api_key', 'private-arkesel-key', 'sms');
    }

    private function template(string $channel = 'sms'): MessageTemplate
    {
        return MessageTemplate::create([
            'name' => 'Test', 'channel' => $channel, 'category' => 'reminder',
            'subject' => $channel === 'email' ? 'Reminder: {{title}}' : null,
            'body' => 'Hello {{name}}, {{title}}.', 'enabled' => true,
        ]);
    }

    private function payload(MessageTemplate $template): array
    {
        return ['template_id' => $template->id, 'recipient' => '024 123 4567', 'variables' => ['name' => 'Ama', 'title' => 'Review']];
    }

    public function test_credentials_are_encrypted_redacted_and_preserved_when_blank(): void
    {
        $this->putJson('/api/v1/settings', ['email' => ['mail_password' => 'smtp-secret'], 'sms' => ['sms_api_key' => 'sms-secret']])
            ->assertOk()->assertJsonPath('settings.email.mail_password', '')->assertJsonPath('settings.sms.sms_api_key_configured', true);
        $this->assertNotSame('smtp-secret', Setting::where('key', 'mail_password')->value('value'));
        $this->assertSame('smtp-secret', Setting::get('mail_password'));
        $this->putJson('/api/v1/settings', ['email' => ['mail_password' => '', 'mail_password_configured' => true]])->assertOk();
        $this->assertSame('smtp-secret', Setting::get('mail_password'));
        $this->getJson('/api/v1/settings')->assertOk()->assertDontSee('smtp-secret')->assertDontSee('sms-secret');
        $this->getJson('/api/v1/settings/export')->assertOk()->assertDontSee('smtp-secret')->assertDontSee('sms-secret');
        $this->seed(SettingSeeder::class);
        $this->assertSame('smtp-secret', Setting::get('mail_password'));
    }

    public function test_settings_validation_is_atomic_and_rejects_key_reassignment(): void
    {
        $old = Setting::get('company_name');
        $this->putJson('/api/v1/settings', ['general' => ['company_name' => 'Changed'], 'email' => ['mail_port' => 99999]])->assertUnprocessable();
        $this->assertSame($old, Setting::get('company_name'));
        $this->putJson('/api/v1/settings', ['general' => ['sms_api_key' => 'leak']])->assertUnprocessable();
        $this->putJson('/api/v1/settings', ['sms' => ['sms_sender_id' => 'TooLongSenderName']])->assertUnprocessable();
        $this->putJson('/api/v1/settings', ['email' => ['mail_mailer' => 'mailgun']])->assertUnprocessable();
    }

    public function test_system_settings_can_be_saved_across_all_settings_groups(): void
    {
        $this->putJson('/api/v1/settings', [
            'general' => ['company_name' => 'Updated Evolex'],
            'notifications' => ['alert_sick_animals' => false],
            'livestock' => ['id_prefix_goat' => 'GOAT'],
            'integrations' => ['google_workspace_domain' => 'updated.example'],
            'security' => ['session_lifetime_minutes' => 180],
        ])->assertOk()
            ->assertJsonPath('settings.general.company_name', 'Updated Evolex')
            ->assertJsonPath('settings.notifications.alert_sick_animals', false)
            ->assertJsonPath('settings.livestock.id_prefix_goat', 'GOAT')
            ->assertJsonPath('settings.integrations.google_workspace_domain', 'updated.example')
            ->assertJsonPath('settings.security.session_lifetime_minutes', 180);
    }

    public function test_system_settings_are_initialized_and_can_be_saved_without_preseeded_settings(): void
    {
        Setting::query()->delete();

        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('settings.general.company_name', 'Evolex Food Ltd');

        Setting::query()->delete();

        $this->putJson('/api/v1/settings', [
            'general' => ['company_name' => 'First Save'],
        ])->assertOk()
            ->assertJsonPath('settings.general.company_name', 'First Save');
    }

    public function test_ordinary_staff_cannot_access_settings_templates_or_send_messages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'farm_employee', 'status' => 'active']));
        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->getJson('/api/v1/settings/export')->assertForbidden();
        $this->getJson('/api/v1/communications/templates')->assertForbidden();
        $this->getJson('/api/v1/communications/messages')->assertForbidden();
        $this->postJson('/api/v1/settings/test-sms', ['recipient' => '+233241234567'])->assertForbidden();
        $this->postJson('/api/v1/communications/messages', [])->assertForbidden();
    }

    public function test_disabled_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'inactive']));
        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_sms_uses_arkesel_normalizes_ghana_numbers_and_claims_only_once(): void
    {
        $this->sms();
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success', 'data' => [['id' => 'provider-1']]])]);
        $response = $this->postJson('/api/v1/communications/messages', $this->payload($this->template()))
            ->assertCreated()->assertJsonPath('status', 'accepted')->assertJsonPath('recipient', '+233241234567')
            ->assertJsonPath('body', 'Hello Ama, Review.')->assertJsonPath('provider_id', 'provider-1');
        app(MessageDispatcher::class)->dispatch(OutboundMessage::findOrFail($response->json('id')));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://sms.arkesel.com/api/v2/sms/send'
            && $r->hasHeader('api-key', 'private-arkesel-key') && $r['recipients'] === ['233241234567']);
    }

    public function test_provider_failure_is_recorded_without_leaking_secrets_or_retrying(): void
    {
        $this->sms();
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'error', 'message' => 'private-arkesel-key'], 401)]);
        $this->postJson('/api/v1/communications/messages', $this->payload($this->template()))
            ->assertCreated()->assertJsonPath('status', 'failed')->assertDontSee('private-arkesel-key');
        $this->artisan('communications:send-due')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_sms_requires_enabled_provider_valid_recipient_and_all_variables(): void
    {
        $template = $this->template();
        $this->postJson('/api/v1/communications/messages', $this->payload($template))->assertUnprocessable();
        $this->sms();
        $this->postJson('/api/v1/communications/messages', [...$this->payload($template), 'recipient' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/communications/messages', [...$this->payload($template), 'variables' => ['name' => 'Ama']])->assertUnprocessable();
        $template->update(['enabled' => false]);
        $this->postJson('/api/v1/communications/messages', $this->payload($template))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_scheduled_messages_are_snapshots_and_only_send_when_due(): void
    {
        $this->sms();
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success'])]);
        $template = $this->template();
        $sendAt = now()->addHours(2);
        $id = $this->postJson('/api/v1/communications/messages', [...$this->payload($template), 'scheduled_at' => $sendAt->toIso8601String()])
            ->assertCreated()->assertJsonPath('status', 'pending')->json('id');
        $template->update(['body' => 'Changed']);
        $this->artisan('communications:send-due')->assertSuccessful();
        Http::assertNothingSent();
        $this->travel(3)->hours();
        $this->artisan('communications:send-due')->assertSuccessful();
        $this->assertSame('accepted', OutboundMessage::find($id)->status);
        Http::assertSent(fn ($r) => $r['message'] === 'Hello Ama, Review.');
        $this->artisan('communications:send-due')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_scheduled_message_can_be_cancelled_but_not_after_claiming(): void
    {
        $this->sms();
        $id = $this->postJson('/api/v1/communications/messages', [...$this->payload($this->template()), 'scheduled_at' => now()->addHour()->toIso8601String()])->assertCreated()->json('id');
        $this->postJson("/api/v1/communications/messages/{$id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson("/api/v1/communications/messages/{$id}/cancel")->assertConflict();
        $this->travel(2)->hours();
        $this->artisan('communications:send-due')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_invalid_past_or_timezone_less_schedules_are_rejected(): void
    {
        $this->sms();
        $payload = $this->payload($this->template());
        $this->postJson('/api/v1/communications/messages', [...$payload, 'scheduled_at' => now()->subMinute()->toIso8601String()])->assertUnprocessable();
        $this->postJson('/api/v1/communications/messages', [...$payload, 'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s')])->assertUnprocessable();
    }

    public function test_template_placeholders_and_email_subjects_are_validated(): void
    {
        $base = ['name' => 'Template', 'channel' => 'email', 'category' => 'meeting', 'subject' => 'Meeting', 'body' => 'Hello {{unknown}}', 'enabled' => true];
        $this->postJson('/api/v1/communications/templates', $base)->assertUnprocessable();
        $this->postJson('/api/v1/communications/templates', [...$base, 'body' => 'Hello {{name}}'])->assertCreated();
        $this->postJson('/api/v1/communications/templates', [...$base, 'body' => 'Hello {{name', 'subject' => 'Meeting'])->assertUnprocessable();
        $this->postJson('/api/v1/communications/templates', [...$base, 'body' => 'Hello', 'subject' => "Bad\nsubject"])->assertUnprocessable();
    }

    public function test_email_test_calls_the_real_mail_service_and_reports_log_mode_truthfully(): void
    {
        $this->mock(OutboundMailService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->with('ama@example.com', Mockery::type('string'), Mockery::type('string'))->andReturn('logged');
        });
        $this->postJson('/api/v1/settings/test-email', ['recipient' => 'ama@example.com'])->assertOk()
            ->assertJsonPath('details.handshake', 'logged')->assertSee('No email was sent');
    }

    public function test_email_test_failure_is_not_simulated_success_or_raw_exception(): void
    {
        $this->mock(OutboundMailService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp-secret'));
        });
        $this->postJson('/api/v1/settings/test-email', ['recipient' => 'ama@example.com'])->assertStatus(502)
            ->assertJsonPath('success', false)->assertDontSee('smtp-secret');
    }

    public function test_saved_smtp_options_and_sender_are_used_by_outbound_mail(): void
    {
        Setting::set('mail_password', 'secret-password', 'email');
        Setting::set('mail_encryption', 'tls', 'email');
        Setting::set('mail_reply_to', 'reply@example.com', 'email');
        $mailer = Mockery::mock(\Illuminate\Mail\Mailer::class);
        Mail::shouldReceive('purge')->once()->with('communications');
        Mail::shouldReceive('mailer')->once()->with('communications')->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->with(Mockery::type('string'), Mockery::on(function ($callback) {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            return $message->getSymfonyMessage()->getReplyTo()[0]->getAddress() === 'reply@example.com';
        }));
        $this->assertSame('accepted', app(OutboundMailService::class)->send('ama@example.com', 'Test', 'Body'));
        $this->assertSame('secret-password', config('mail.mailers.communications.password'));
        $this->assertTrue(config('mail.mailers.communications.require_tls'));
    }

    public function test_email_templates_use_the_same_service(): void
    {
        $this->mock(OutboundMailService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->with('ama@example.com', 'Reminder: Review', 'Hello Ama, Review.')->andReturn('accepted');
        });
        $this->postJson('/api/v1/communications/messages', [...$this->payload($this->template('email')), 'recipient' => 'ama@example.com'])
            ->assertCreated()->assertJsonPath('status', 'accepted');
    }
}
