# MFS Webhook HMAC Key Rotation Procedure

To ensure the security of MFS (bKash/Nagad) webhook integrations, rotate the `MFS_WEBHOOK_HMAC_KEY` periodically or if a breach is suspected.

## Procedure

1. **Generate a new secure key:**
   Run the following command in your terminal to generate a strong random key:
   ```bash
   openssl rand -base64 32
   ```

2. **Update the secret store:**
   Update `BKASH_WEBHOOK_SECRET` and `NAGAD_WEBHOOK_SECRET` in your environment, or the `bkash_secret` and `nagad_secret` Docker secrets. `MFS_WEBHOOK_HMAC_KEY` is supported as a shared fallback when gateway-specific values are not set.

3. **Update the payment provider:**
   Update the HMAC secret in your bKash/Nagad merchant developer portal to match the new key.

4. **Deploy the configuration:**
   Reload the application containers so they pick up the new key.
   ```bash
   docker compose -f infra/docker-compose.yml up -d --force-recreate laravel_app
   ```

5. **Verify:**
   Test a webhook callback from the provider to ensure HMAC verification succeeds with the new key.
