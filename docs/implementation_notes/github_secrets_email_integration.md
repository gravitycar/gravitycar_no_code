# GitHub Secrets Integration for Email Notifications

## Overview
The `notify.sh` script uses environment variables that are populated from GitHub repository secrets during CI/CD execution. This ensures secure handling of sensitive email credentials.

## Required GitHub Repository Secrets

You need to configure the following secrets in your GitHub repository (Settings → Secrets and variables → Actions):

### Email/SMTP Configuration Secrets (from Implementation Plan)
```
NOTIFICATION_EMAIL_HOST     # SMTP server hostname (e.g., smtp.gmail.com)
NOTIFICATION_EMAIL_USER     # SMTP username and sender email address
NOTIFICATION_EMAIL_PASSWORD # SMTP password for authentication
SMTP_PORT                   # SMTP port (usually 587 for TLS, 465 for SSL) - Optional, defaults to 587
```

### Production Deployment Secrets (already configured)
```
PRODUCTION_SSH_HOST         # Production server hostname (was PRODUCTION_HOST)
PRODUCTION_SSH_USER         # SSH username for production server (was PRODUCTION_USER)  
PRODUCTION_SSH_KEY          # Private SSH key for production access
PRODUCTION_DB_PASSWORD      # Database password for production (was DB_PASSWORD)
PRODUCTION_DB_HOST          # Database host for production
PRODUCTION_DB_NAME          # Database name for production
PRODUCTION_DB_USER          # Database user for production
TMDB_API_KEY               # TMDB API key for movie data
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
    NOTIFICATION_EMAIL_PASSWORD: ${{ secrets.NOTIFICATION_EMAIL_PASSWORD }}
    NOTIFICATION_EMAIL_HOST: ${{ secrets.NOTIFICATION_EMAIL_HOST }}
    NOTIFICATION_EMAIL_USER: ${{ secrets.NOTIFICATION_EMAIL_USER }}
    SMTP_PORT: ${{ secrets.SMTP_PORT || '587' }}
```

### 2. Script Environment Variable Usage
In `scripts/notify.sh`, these environment variables are mapped from implementation plan secrets:

```bash
# Email configuration mapped from implementation plan secrets
FROM_EMAIL="${NOTIFICATION_EMAIL_USER:-$DEFAULT_FROM_EMAIL}"
TO_EMAIL="${NOTIFICATION_EMAIL_USER:-$DEFAULT_TO_EMAIL}"  # Same email for both
SMTP_HOST="${NOTIFICATION_EMAIL_HOST:-$DEFAULT_SMTP_HOST}"
SMTP_PORT="${SMTP_PORT:-$DEFAULT_SMTP_PORT}"
SMTP_USER="${NOTIFICATION_EMAIL_USER:-$FROM_EMAIL}"
SMTP_PASSWORD="${NOTIFICATION_EMAIL_PASSWORD:-}"
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

**Example for Gmail SMTP (matching implementation plan):**
```
NOTIFICATION_EMAIL_PASSWORD: your-app-password
NOTIFICATION_EMAIL_HOST: smtp.gmail.com
NOTIFICATION_EMAIL_USER: notifications@gravitycar.com
SMTP_PORT: 587
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

For a complete Gmail setup, configure these secrets (matching implementation plan):

```
NOTIFICATION_EMAIL_PASSWORD=abcd-efgh-ijkl-mnop          # Gmail app password
NOTIFICATION_EMAIL_HOST=smtp.gmail.com
NOTIFICATION_EMAIL_USER=notifications@gravitycar.com
SMTP_PORT=587
```

## Integration Status

- ✅ **GitHub Actions**: Properly passes all SMTP secrets as environment variables
- ✅ **Notify Script**: Reads environment variables with secure fallbacks
- ✅ **Security**: No credentials exposed in logs or console output
- 🔄 **Email Sending**: Basic framework ready for Phase 5 implementation

The notification system is now properly configured to use GitHub secrets for all SMTP credentials while maintaining secure defaults for local development!