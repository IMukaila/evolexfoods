<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Info
            ['group' => 'general', 'key' => 'company_name', 'value' => 'Evolex Food Ltd', 'type' => 'string', 'description' => 'Official trading and brand name'],
            ['group' => 'general', 'key' => 'legal_name', 'value' => 'Evolex Food & Agricultural Enterprises Limited', 'type' => 'string', 'description' => 'Registered corporate entity name'],
            ['group' => 'general', 'key' => 'tagline', 'value' => 'Traceable Livestock & Precision Agricultural Operations', 'type' => 'string', 'description' => 'Company mission tagline'],
            ['group' => 'general', 'key' => 'registration_no', 'value' => 'GH-EVF-2024-9812', 'type' => 'string', 'description' => 'Corporate registration number'],
            ['group' => 'general', 'key' => 'tax_id', 'value' => 'C0028919283X', 'type' => 'string', 'description' => 'Tax Identification Number (TIN)'],
            ['group' => 'general', 'key' => 'primary_email', 'value' => 'operations@evolexfood.com', 'type' => 'string', 'description' => 'Primary operational inquiries email'],
            ['group' => 'general', 'key' => 'support_email', 'value' => 'support@evolexfood.com', 'type' => 'string', 'description' => 'Internal user support email'],
            ['group' => 'general', 'key' => 'phone_number', 'value' => '+233 20 890 1234', 'type' => 'string', 'description' => 'Primary telephone line'],
            ['group' => 'general', 'key' => 'secondary_phone', 'value' => '+233 24 555 0199', 'type' => 'string', 'description' => 'Farm emergency contact line'],
            ['group' => 'general', 'key' => 'farm_location', 'value' => 'Plot 42 Savanna Agricultural Corridor, Afienya-Dawhenya District, Greater Accra, Ghana', 'type' => 'string', 'description' => 'Physical farm location'],
            ['group' => 'general', 'key' => 'head_office', 'value' => 'Suite 401, Ridge Towers, 14 Barnes Road, Accra, Ghana', 'type' => 'string', 'description' => 'Executive & administrative head office'],
            ['group' => 'general', 'key' => 'currency', 'value' => 'GHS', 'type' => 'string', 'description' => 'Default system accounting currency'],
            ['group' => 'general', 'key' => 'currency_symbol', 'value' => 'GH₵', 'type' => 'string', 'description' => 'Display currency symbol'],
            ['group' => 'general', 'key' => 'timezone', 'value' => 'Africa/Accra', 'type' => 'string', 'description' => 'Primary operational timezone'],
            ['group' => 'general', 'key' => 'date_format', 'value' => 'YYYY-MM-DD', 'type' => 'string', 'description' => 'Standard date presentation format'],
            ['group' => 'general', 'key' => 'time_format', 'value' => '24h', 'type' => 'string', 'description' => 'Time presentation (12h or 24h)'],
            ['group' => 'general', 'key' => 'fiscal_year_start', 'value' => 'January', 'type' => 'string', 'description' => 'Starting month of fiscal calendar'],
            ['group' => 'general', 'key' => 'operating_hours', 'value' => '06:00 - 18:00 GMT', 'type' => 'string', 'description' => 'Core farm working hours'],

            // Email & SMTP Settings
            ['group' => 'email', 'key' => 'mail_mailer', 'value' => 'smtp', 'type' => 'string', 'description' => 'Mail protocol or driver'],
            ['group' => 'email', 'key' => 'mail_host', 'value' => 'smtp.gmail.com', 'type' => 'string', 'description' => 'SMTP host server address'],
            ['group' => 'email', 'key' => 'mail_port', 'value' => '465', 'type' => 'integer', 'description' => 'SMTP server communication port'],
            ['group' => 'email', 'key' => 'mail_username', 'value' => 'evolexfoods@gmail.com', 'type' => 'string', 'description' => 'SMTP authentication username'],
            ['group' => 'email', 'key' => 'mail_password', 'value' => '', 'type' => 'string', 'description' => 'SMTP account password / app password'],
            ['group' => 'email', 'key' => 'mail_encryption', 'value' => 'ssl', 'type' => 'string', 'description' => 'Encryption protocol (tls, ssl, or none)'],
            ['group' => 'email', 'key' => 'mail_from_address', 'value' => 'evolexfoods@gmail.com', 'type' => 'string', 'description' => 'Default sender email address'],
            ['group' => 'email', 'key' => 'mail_from_name', 'value' => 'Evolex Operations Hub', 'type' => 'string', 'description' => 'Default sender display name'],
            ['group' => 'email', 'key' => 'mail_reply_to', 'value' => 'evolexfoods@gmail.com', 'type' => 'string', 'description' => 'Reply-to email destination'],
            ['group' => 'email', 'key' => 'smtp_auth', 'value' => '1', 'type' => 'boolean', 'description' => 'Require SMTP server authentication'],
            ['group' => 'email', 'key' => 'send_welcome_email', 'value' => '1', 'type' => 'boolean', 'description' => 'Dispatch onboarding email upon new staff creation'],
            ['group' => 'email', 'key' => 'email_footer_text', 'value' => 'Sent automatically by Evolex Digital Operations Platform. For operational queries contact evolexfoods@gmail.com.', 'type' => 'string', 'description' => 'Standard compliance footer for outbound system emails'],

            // Notifications & Alert Triggers
            ['group' => 'notifications', 'key' => 'alert_sick_animals', 'value' => '1', 'type' => 'boolean', 'description' => 'Trigger immediate alert when sick animal is flagged'],
            ['group' => 'notifications', 'key' => 'alert_mortality_events', 'value' => '1', 'type' => 'boolean', 'description' => 'Send urgent alert upon any reported animal death'],
            ['group' => 'notifications', 'key' => 'alert_low_feed', 'value' => '1', 'type' => 'boolean', 'description' => 'Send notification when feed cover drops below minimum threshold'],
            ['group' => 'notifications', 'key' => 'alert_po_approval_needed', 'value' => '1', 'type' => 'boolean', 'description' => 'Notify executive/ops approvers when purchase request exceeds budget'],
            ['group' => 'notifications', 'key' => 'alert_maintenance_due', 'value' => '1', 'type' => 'boolean', 'description' => 'Alert technical team for preventive asset maintenance schedules'],
            ['group' => 'notifications', 'key' => 'alert_quarantine_breach', 'value' => '1', 'type' => 'boolean', 'description' => 'Send alert for quarantine pen movements'],
            ['group' => 'notifications', 'key' => 'alert_daily_attendance_gap', 'value' => '0', 'type' => 'boolean', 'description' => 'Notify manager if daily farm attendance has unlogged staff'],
            ['group' => 'notifications', 'key' => 'weekly_ceo_digest', 'value' => '1', 'type' => 'boolean', 'description' => 'Automate dispatch of weekly owner & CEO command summary'],
            ['group' => 'notifications', 'key' => 'weekly_digest_day', 'value' => 'Monday', 'type' => 'string', 'description' => 'Day of the week for automated executive digest'],
            ['group' => 'notifications', 'key' => 'weekly_digest_time', 'value' => '07:30', 'type' => 'string', 'description' => 'Time to dispatch executive digest'],
            ['group' => 'notifications', 'key' => 'alert_recipients', 'value' => 'owner@evolex.com, ops@evolex.com, farm@evolex.com', 'type' => 'string', 'description' => 'Comma-separated recipient emails for operational broadcasts'],
            ['group' => 'notifications', 'key' => 'urgent_sms_alerts', 'value' => '1', 'type' => 'boolean', 'description' => 'Also dispatch SMS for Critical severity alerts'],
            ['group' => 'notifications', 'key' => 'urgent_sms_phone', 'value' => '+233 20 890 1234', 'type' => 'string', 'description' => 'Target mobile number for urgent SMS alerts'],

            // Livestock & Farm Operational Rules
            ['group' => 'livestock', 'key' => 'id_prefix_goat', 'value' => 'EV-GT', 'type' => 'string', 'description' => 'Tag code prefix for goats'],
            ['group' => 'livestock', 'key' => 'id_prefix_sheep', 'value' => 'EV-SH', 'type' => 'string', 'description' => 'Tag code prefix for sheep'],
            ['group' => 'livestock', 'key' => 'id_prefix_cattle', 'value' => 'EV-CT', 'type' => 'string', 'description' => 'Tag code prefix for cattle'],
            ['group' => 'livestock', 'key' => 'id_prefix_asset', 'value' => 'EV-AS', 'type' => 'string', 'description' => 'Tag code prefix for fixed assets'],
            ['group' => 'livestock', 'key' => 'id_prefix_employee', 'value' => 'EV-EMP', 'type' => 'string', 'description' => 'Employee staff ID code prefix'],
            ['group' => 'livestock', 'key' => 'default_quarantine_days', 'value' => '21', 'type' => 'integer', 'description' => 'Mandatory quarantine period for newly arrived livestock'],
            ['group' => 'livestock', 'key' => 'mortality_alert_threshold', 'value' => '3.0', 'type' => 'float', 'description' => 'Herd monthly mortality rate percentage that triggers CEO alert'],
            ['group' => 'livestock', 'key' => 'feed_low_threshold_days', 'value' => '14', 'type' => 'integer', 'description' => 'Days of remaining feed stock that triggers replenishment PO'],
            ['group' => 'livestock', 'key' => 'gestation_days_goat', 'value' => '150', 'type' => 'integer', 'description' => 'Expected gestation days for goats'],
            ['group' => 'livestock', 'key' => 'gestation_days_sheep', 'value' => '150', 'type' => 'integer', 'description' => 'Expected gestation days for sheep'],
            ['group' => 'livestock', 'key' => 'gestation_days_cattle', 'value' => '283', 'type' => 'integer', 'description' => 'Expected gestation days for cattle'],
            ['group' => 'livestock', 'key' => 'wean_age_days_goat', 'value' => '90', 'type' => 'integer', 'description' => 'Standard weaning age for kids (days)'],
            ['group' => 'livestock', 'key' => 'wean_age_days_sheep', 'value' => '90', 'type' => 'integer', 'description' => 'Standard weaning age for lambs (days)'],
            ['group' => 'livestock', 'key' => 'wean_age_days_cattle', 'value' => '210', 'type' => 'integer', 'description' => 'Standard weaning age for calves (days)'],

            // Procurement approval matrix (§44–47)
            ['group' => 'procurement', 'key' => 'procurement_executive_threshold_ghs', 'value' => '1000', 'type' => 'float', 'description' => 'Estimated cost (GH₵) above which a purchase request routes directly to Executive approval (Tier 2); at or below it a Supervisor may approve (Tier 1)'],

            // Integrations & External Connected Stack
            ['group' => 'integrations', 'key' => 'google_workspace_connected', 'value' => '1', 'type' => 'boolean', 'description' => 'Google Workspace SSO and Drive synchronization status'],
            ['group' => 'integrations', 'key' => 'google_workspace_domain', 'value' => 'evolex.com', 'type' => 'string', 'description' => 'Registered Google Workspace corporate domain'],
            ['group' => 'integrations', 'key' => 'google_drive_backup_folder', 'value' => 'Evolex Hub Archives', 'type' => 'string', 'description' => 'Google Drive root directory for automated backups'],
            ['group' => 'integrations', 'key' => 'odoo_erp_connected', 'value' => '1', 'type' => 'boolean', 'description' => 'Odoo Community ERP procurement & accounting integration'],
            ['group' => 'integrations', 'key' => 'odoo_erp_url', 'value' => 'https://odoo.evolex.local:8069', 'type' => 'string', 'description' => 'Odoo XML-RPC / JSON-RPC server endpoint'],
            ['group' => 'integrations', 'key' => 'odoo_erp_db', 'value' => 'evolex_production', 'type' => 'string', 'description' => 'Odoo target database identifier'],
            ['group' => 'integrations', 'key' => 'odoo_erp_user', 'value' => 'api_connector@evolex.com', 'type' => 'string', 'description' => 'Odoo service account user'],
            ['group' => 'integrations', 'key' => 'hubspot_connected', 'value' => '1', 'type' => 'boolean', 'description' => 'HubSpot CRM sales pipeline bidirectional sync'],
            ['group' => 'integrations', 'key' => 'hubspot_portal_id', 'value' => '48201923', 'type' => 'string', 'description' => 'HubSpot Portal/Hub ID'],
            ['group' => 'integrations', 'key' => 'whatsapp_cloud_connected', 'value' => '1', 'type' => 'boolean', 'description' => 'WhatsApp Cloud Business API status for automated messaging'],
            ['group' => 'integrations', 'key' => 'whatsapp_sender_id', 'value' => '+233 24 555 0199', 'type' => 'string', 'description' => 'WhatsApp verified business number'],
            ['group' => 'integrations', 'key' => 'cloudflare_cdn_enabled', 'value' => '1', 'type' => 'boolean', 'description' => 'Cloudflare edge caching and DDoS security layer'],

            // Security & System Governance
            ['group' => 'security', 'key' => 'session_lifetime_minutes', 'value' => '120', 'type' => 'integer', 'description' => 'Session inactivity timeout in minutes'],
            ['group' => 'security', 'key' => 'enforce_two_factor', 'value' => '0', 'type' => 'boolean', 'description' => 'Enforce 2FA/MFA for all management and vet roles'],
            ['group' => 'security', 'key' => 'password_expiry_days', 'value' => '90', 'type' => 'integer', 'description' => 'Password rotation interval in days (0 for disabled)'],
            ['group' => 'security', 'key' => 'max_failed_login_attempts', 'value' => '5', 'type' => 'integer', 'description' => 'Failed password attempts before temporary IP lock'],
            ['group' => 'security', 'key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'description' => 'Put Evolex Hub into maintenance mode for field sync'],
            ['group' => 'security', 'key' => 'auto_backup_schedule', 'value' => 'daily', 'type' => 'string', 'description' => 'Database automated backup cadence (daily, weekly, monthly)'],
            ['group' => 'security', 'key' => 'audit_retention_days', 'value' => '365', 'type' => 'integer', 'description' => 'Keep system audit trails for number of days'],
            ['group' => 'security', 'key' => 'debug_telemetry', 'value' => '0', 'type' => 'boolean', 'description' => 'Log verbose debug telemetry in storage/logs'],
        ];

        foreach ($settings as $setting) {
            if (!Setting::where('key', $setting['key'])->exists()) {
                Setting::set($setting['key'], match ($setting['type']) {
                    'boolean' => $setting['value'] === '1',
                    'integer' => (int) $setting['value'],
                    'float' => (float) $setting['value'],
                    default => $setting['value'],
                }, $setting['group'], $setting['type'], $setting['description']);
            }
        }
    }
}
