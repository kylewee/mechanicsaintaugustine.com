# Mechanic Saint Augustine Website

A comprehensive mobile mechanic service management platform featuring:

- **Customer-facing website** for service requests and quotes
- **CRM Integration** with Rukovoditel for lead management
- **Modern Go Backend** (API-first architecture in development)
- **Legacy PHP Application** for customer/mechanic registration and booking
- **Twilio Integration** for SMS notifications and call recording
- **AI-powered** call transcription and data extraction
- **Quote Generation** with automated pricing from labor catalog

## Architecture

This project consists of multiple components:

- `backend/` - Modern Go API server (clean architecture)
- `Mobile-mechanic/` - Legacy PHP application
- `crm/` - Rukovoditel CRM system
- `api/` - PHP API endpoints for quotes and SMS
- `voice/` - Twilio call recording and transcription
- `admin/` - Admin tools for dispatch and parts ordering

## Security

**IMPORTANT**: This project uses environment variables for all sensitive configuration.

Required environment variables:
- Database credentials (see `.env.example`)
- Twilio API credentials
- OpenAI API key
- CRM credentials

Never commit `.env` files or hardcode credentials.

## Setup

1. Copy `.env.example` to `.env` and configure your credentials
2. Set up database (MySQL/MariaDB for PHP, PostgreSQL for Go backend)
3. Configure Caddy server or your preferred web server
4. Install dependencies (PHP, Go, Composer)

See `docs/` directory for detailed documentation.
