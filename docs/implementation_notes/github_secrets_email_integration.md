# GitHub Secrets Integration for Email Notifications

## Overview
The `notify.sh` script uses environment variables that are populated from GitHub repository secrets during CI/CD execution. This ensures secure handling of sensitive email credentials.

## Required GitHub Repository Secrets

You need to configure the following secrets in your GitHub repository (Settings → Secrets and variables → Actions):

### Email/SMTP Configuration Secrets
```
EMAIL_PASSWORD           # SMTP password for authentication
SMTP_HOST               # SMTP server hostname (e.g., smtp.gmail.com)
SMTP_PORT               # SMTP port (usually 587 for TLS, 465 for SSL)
SMTP_USER               # SMTP username (often same as from email)
NOTIFICATION_FROM_EMAIL # Sender email address
NOTIFICATION_TO_EMAIL   # Recipient email address(es)
```

### Production Deployment Secrets (already configured)
```
PRODUCTION_HOST         # Production server hostname
PRODUCTION_USER         # SSH username for production server
PRODUCTION_SSH_KEY      # Private SSH key for production access
DB_PASSWORD            # Database password for production
```

## How Environment Variables Flow

### 1. GitHub Actions Workflow
In `.github/workflows/deploy.yml`, secrets are passed as environment variables:

```yaml
- name: Send notification
  run: |
    chmod +x scripts/notify.sh
    scripts/notify.sh
  env:
    # ... other variables ...
    EMAIL_PASSWORD: ${{ secrets.EMAIL_PASSWORD }}
    SMTP_HOST: ${{ secrets.SMTP_HOST }}
    SMTP_PORT: ${{ secrets.SMTP_PORT }}
    SMTP_USER: ${{ secrets.SMTP_USER }}
    NOTIFICATION_FROM_EMAIL: ${{ secrets.NOTIFICATION_FROM_EMAIL }}
    NOTIFICATION_TO_EMAIL: ${{ secrets.NOTIFICATION_TO_EMAIL }}
```

### 2. Script Environment Variable Usage
In `scripts/notify.sh`, these environment variables are used with fallback defaults:

```bash
# Email configuration from environment or defaults
FROM_EMAIL="${NOTIFICATION_FROM_EMAIL:-$DEFAULT_FROM_EMAIL}"
TO_EMAIL="${NOTIFICATION_TO_EMAIL:-$DEFAULT_TO_EMAIL}"
SMTP_HOST="${SMTP_HOST:-$DEFAULT_SMTP_HOST}"
SMTP_PORT="${SMTP_PORT:-$DEFAULT_SMTP_PORT}"
SMTP_USER="${SMTP_USER:-$FROM_EMAIL}"
SMTP_PASSWORD="${EMAIL_PASSWORD:-}"
```

## Default Fallback Values

If secrets are not configured, the script uses these defaults:

```bash
DEFAULT_FROM_EMAIL="mike@gravitycar.com"
DEFAULT_TO_EMAIL="mike@gravitycar.com"
DEFAULT_SMTP_HOST="gravitycar.com"
DEFAULT_SMTP_PORT="587"
```

## Email Notification Features

### Current Implementation (Phase 3)
- ✅ Console notifications with deployment status
- ✅ Log file notifications for record keeping
- ✅ GitHub Actions environment variable integration
- ✅ Secure credential handling via repository secrets

### Future Implementation (Phase 5)
- 📧 Actual SMTP email sending
- 📱 Slack/Teams integration
- 📊 Enhanced deployment reporting
- 🔔 Real-time notifications

## Setting Up Email Notifications

### Step 1: Configure GitHub Secrets
1. Go to your repository on GitHub
2. Navigate to Settings → Secrets and variables → Actions
3. Click "New repository secret"
4. Add each required secret:

**Example for Gmail SMTP:**
```
EMAIL_PASSWORD: your-app-password
SMTP_HOST: smtp.gmail.com
SMTP_PORT: 587
SMTP_USER: your-email@gmail.com
NOTIFICATION_FROM_EMAIL: your-email@gmail.com
NOTIFICATION_TO_EMAIL: recipient@example.com
```

### Step 2: Verify Integration
Run a deployment (even in dry-run mode) to verify that:
1. Environment variables are properly passed from secrets
2. Console notifications display correctly
3. Log files contain deployment records

### Step 3: Test Email Functionality (Future)
When Phase 5 email implementation is added, test by:
1. Running a deployment
2. Checking that emails are sent
3. Verifying email content and formatting

## Security Considerations

### ✅ Secure Practices
- Secrets are never logged or displayed in console output
- Environment variables are only available during script execution
- SMTP passwords use app-specific passwords, not account passwords
- All sensitive data is handled through GitHub's encrypted secrets

### ⚠️ Important Notes
- Never commit SMTP credentials to the repository
- Use app-specific passwords for email services (Gmail, Outlook, etc.)
- Regularly rotate email passwords and update secrets
- Test email functionality in a non-production environment first

## Troubleshooting

### Issue: Environment Variables Not Available
**Problem**: Script uses default values instead of secrets
**Solution**: Verify secrets are configured in GitHub repository settings

### Issue: SMTP Authentication Failed
**Problem**: Email sending fails with authentication error
**Solution**: 
1. Verify SMTP credentials are correct
2. Use app-specific password for Gmail/Outlook
3. Check SMTP host and port settings

### Issue: Notifications Not Sent
**Problem**: Script runs but no notifications received
**Solution**: 
1. Check logs for error messages
2. Verify email addresses are correct
3. Test SMTP settings manually

## Example Secret Configuration

For a complete Gmail setup, configure these secrets:

```
EMAIL_PASSWORD=abcd-efgh-ijkl-mnop          # Gmail app password
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
NOTIFICATION_FROM_EMAIL=your-email@gmail.com
NOTIFICATION_TO_EMAIL=team@yourcompany.com
```

## Integration Status

- ✅ **GitHub Actions**: Properly passes all SMTP secrets as environment variables
- ✅ **Notify Script**: Reads environment variables with secure fallbacks
- ✅ **Security**: No credentials exposed in logs or console output
- 🔄 **Email Sending**: Basic framework ready for Phase 5 implementation

The notification system is now properly configured to use GitHub secrets for all SMTP credentials while maintaining secure defaults for local development!