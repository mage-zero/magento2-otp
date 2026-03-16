# MageZero_Otp

Magento 2 module that adds an email-approval OTP step to customer login.

## Customer Flow

1. Customer submits email + password on the standard login form.
2. A login approval email is sent with a link.
3. Customer is redirected to a waiting page (`check your email inbox for a login code`).
4. Opening the email link shows a one-time code in that browser.
5. The original login tab polls and automatically moves to code-entry once approved.
6. Customer enters the code to complete login.

New customer registrations that would normally auto-login are also routed through the same OTP flow.

Incorrect code attempts are server-validated and limited to `3` attempts per challenge.

## Technical Notes

- Login interception: plugin on `Magento\Customer\Controller\Account\LoginPost`.
- Challenge persistence: `mz_customer_otp_challenge` table.
- Routes:
  - `otp/auth/pending`
  - `otp/auth/status`
  - `otp/auth/approve`
  - `otp/auth/verify`
  - `otp/auth/submit`
- Email template id: `mz_customer_login_otp_request`.

## Defaults

Configured in `etc/config.xml`:

- `mz_otp/general/enabled = 1`
- `mz_otp/general/challenge_ttl_minutes = 10`
- `mz_otp/general/max_attempts = 3`
- `mz_otp/general/poll_interval_ms = 2000`
