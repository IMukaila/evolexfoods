import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthService, TwoFactorSetupResponse, TwoFaMethod } from '../../core/services/auth.service';

type ActivationStep = 'select-method' | 'send-otp' | 'verify';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})
export class LoginComponent implements OnInit {
  // ─── Credentials ─────────────────────────────────────────────────────────
  email = '';
  password = '';

  // ─── Login flow ───────────────────────────────────────────────────────────
  step: 'credentials' | '2fa' = 'credentials';
  loading = false;
  error = '';

  // ─── 2FA Challenge (for already-activated users at login) ─────────────────
  twoFactorCode = '';
  twoFaType: TwoFaMethod = 'totp';
  twoFaChallengeHint = '';
  rememberDevice = false;
  resending = false;
  resendSuccess = '';

  // ─── Activation Modal ─────────────────────────────────────────────────────
  showActivationModal = false;
  activating = false;
  activationError = '';
  activationSuccess = false;
  setupData: TwoFactorSetupResponse | null = null;
  copiedKey = false;
  copiedCodes = false;
  activationRecoveryCodes: string[] = [];

  // Activation multi-step
  activationStep: ActivationStep = 'select-method';
  selectedMethod: TwoFaMethod = 'totp';
  activationCode = '';
  otpSent = false;
  otpSending = false;
  otpSendHint = '';

  constructor(public auth: AuthService, private router: Router) {}

  ngOnInit(): void {
    if (this.auth.isLoggedIn()) {
      this.router.navigate(['/ceo']);
    }
  }

  // ─── LOGIN ────────────────────────────────────────────────────────────────

  submit(): void {
    this.loading = true;
    this.error = '';

    const code        = this.step === '2fa' ? this.twoFactorCode.trim() : undefined;
    const deviceToken = localStorage.getItem('evolex_device_token') ?? undefined;
    const remember    = this.step === '2fa' ? this.rememberDevice : undefined;

    this.auth.login(this.email, this.password, code, remember, deviceToken).subscribe({
      next: (res) => {
        this.loading = false;

        if (res.requires_2fa) {
          this.step               = '2fa';
          this.twoFactorCode      = '';
          this.twoFaType          = (res.two_factor_type as TwoFaMethod) || 'totp';
          this.twoFaChallengeHint = res.message || '';
          return;
        }

        if (res.user) {
          this.router.navigate(['/ceo']);
        }
      },
      error: (err) => {
        this.loading = false;
        if (this.step === '2fa') {
          this.error = err?.error?.errors?.code?.[0] || err?.error?.message || 'Invalid or expired code. Please try again.';
        } else {
          this.error = err?.error?.errors?.email?.[0] || 'Unable to sign in with those credentials.';
        }
      }
    });
  }

  backToCredentials(): void {
    this.step          = 'credentials';
    this.twoFactorCode = '';
    this.error         = '';
    this.rememberDevice = false;
  }

  resendOtp(): void {
    if (this.resending) return;
    this.resending    = true;
    this.resendSuccess = '';
    this.auth.sendOtp(this.twoFaType as 'email' | 'whatsapp').subscribe({
      next: (res) => {
        this.resending    = false;
        this.resendSuccess = res.message;
        setTimeout(() => (this.resendSuccess = ''), 6000);
      },
      error: () => {
        this.resending = false;
        this.error     = 'Failed to resend code. Please try again.';
      }
    });
  }

  // ─── ACTIVATION MODAL ─────────────────────────────────────────────────────

  openActivationModal(): void {
    this.showActivationModal   = true;
    this.activationError       = '';
    this.activationSuccess     = false;
    this.activationCode        = '';
    this.activationStep        = 'select-method';
    this.selectedMethod        = 'totp';
    this.otpSent               = false;
    this.otpSendHint           = '';
    this.activationRecoveryCodes = [];

    this.auth.setup2fa().subscribe({
      next: (data) => { this.setupData = data; },
      error: ()     => { this.activationError = 'Could not load 2FA setup. Please retry.'; }
    });
  }

  selectMethod(m: TwoFaMethod): void {
    this.selectedMethod = m;
  }

  proceedToVerify(): void {
    this.activationCode  = '';
    this.activationError = '';
    this.otpSent         = false;
    this.otpSendHint     = '';
    this.activationStep  = this.selectedMethod === 'totp' ? 'verify' : 'send-otp';
  }

  sendActivationOtp(): void {
    if (this.otpSending) return;
    this.otpSending      = true;
    this.activationError = '';
    this.auth.sendOtp(this.selectedMethod as 'email' | 'whatsapp').subscribe({
      next: (res) => {
        this.otpSending     = false;
        this.otpSent        = true;
        this.otpSendHint    = res.message;
        this.activationStep = 'verify';
      },
      error: (err) => {
        this.otpSending      = false;
        this.activationError = err?.error?.message || 'Failed to send code. Please try again.';
      }
    });
  }

  submitActivation(): void {
    if (!this.activationCode || this.activationCode.trim().length < 6) {
      this.activationError = 'Please enter a valid 6-digit code.';
      return;
    }
    this.activating      = true;
    this.activationError = '';

    this.auth.activate2fa(this.activationCode.trim(), this.selectedMethod).subscribe({
      next: (res) => {
        this.activating          = false;
        this.activationRecoveryCodes = res.recovery_codes || [];
        this.activationSuccess   = true;
        setTimeout(() => {
          this.showActivationModal = false;
          this.router.navigate(['/ceo']);
        }, 3000);
      },
      error: (err) => {
        this.activating      = false;
        this.activationError = err?.error?.message || 'Invalid code. Please try again.';
      }
    });
  }

  backToMethodSelect(): void {
    this.activationStep  = 'select-method';
    this.activationCode  = '';
    this.activationError = '';
    this.otpSent         = false;
  }

  copySecretKey(): void {
    if (this.setupData?.secret) {
      navigator.clipboard.writeText(this.setupData.secret);
      this.copiedKey = true;
      setTimeout(() => (this.copiedKey = false), 2500);
    }
  }

  copyRecoveryCodes(): void {
    const codes = this.activationRecoveryCodes.length
      ? this.activationRecoveryCodes
      : (this.setupData?.recovery_codes || []);
    if (codes.length) {
      navigator.clipboard.writeText(codes.join('\n'));
      this.copiedCodes = true;
      setTimeout(() => (this.copiedCodes = false), 2500);
    }
  }

  get methodLabel(): string {
    const map: Record<TwoFaMethod, string> = {
      totp:      'Authenticator App',
      email:     'Email',
      whatsapp:  'WhatsApp'
    };
    return map[this.selectedMethod];
  }

  get challengeMethodLabel(): string {
    const map: Record<TwoFaMethod, string> = {
      totp:      'Authenticator App',
      email:     'Email OTP',
      whatsapp:  'WhatsApp OTP'
    };
    return map[this.twoFaType] || 'Authenticator App';
  }
}
