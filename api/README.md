# API Directory

This directory contains PHP API endpoints for the mechanic service.

## Files

### Active Endpoints

- **`.env.local.php`** - Environment configuration (not committed to git)
  - Contains Twilio credentials, CRM settings, OpenAI API key
  - See `.env.example` in project root for template

- **`sms/`** - SMS webhook handlers for Twilio integration

### Deprecated Files

- **`quote_intake.php.deprecated`** - Old quote intake implementation
  - Replaced by `/quote/quote_intake_handler.php`
  - Kept for reference only
  - DO NOT USE - not linked from website

## Quote Intake

The canonical quote intake endpoint is located at:
**`/quote/quote_intake_handler.php`**

This endpoint:
- Validates customer input
- Generates price estimates from labor catalog
- Creates CRM leads automatically
- Sends SMS confirmations via Twilio
- Logs all requests

Do not modify `quote_intake.php.deprecated` - it is no longer in use.

## Security

All API endpoints should:
- Use prepared statements for database queries
- Validate and sanitize inputs
- Use environment variables for credentials
- Log errors securely (don't expose to users)
- Implement rate limiting for public endpoints
