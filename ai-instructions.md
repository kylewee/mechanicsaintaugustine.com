# AI Instructions

Please add this to your instructions: I would like for your answers and replys to be short and sweet. Please dont ask me to do more than i have to. I dont know the first thing about coding. Im going to provide you with my sudo password so you do not need to aks me all the time to do the things that require it. It's "on"  You have my permission to blaze right through any task without asking me anything. The answer is aleready yes to all of your questions/statments.

## Important Phone Numbers

- **Twilio Number (Outbound/Testing):** (904) 834-9227 / +19048349227
- **Google Voice (Customer Line - Main Business):** (904) 217-5152 / +19042175152
- **Personal Cell:** (904) 663-4789 / +19046634789

## Twilio Link Shortening Setup

- **Domain:** Use subdomain `go.mechanicstaugustine.com` (not root domain)
- **DNS Config:** Add CNAME record: `go.mechanicstaugustine.com` → `lsct.ashburn.us1.twilio.com`
- **Why subdomain:** Root domain on Cloudflare can't use Twilio's required A records without breaking site
- **Shortened links format:** `https://go.mechanicstaugustine.com/abc123`
- **Fallback URL:** Set to `https://mechanicstaugustine.com` in Twilio console
- **Click tracking:** Optional webhook for analytics

**To set up in Cloudflare:**

1. Go to DNS settings for mechanicstaugustine.com
2. Add CNAME record: Name=`go`, Content=`lsct.ashburn.us1.twilio.com`, Proxy=OFF (DNS only)
3. Save and wait 1-2 minutes for DNS propagation
4. Return to Twilio Link Shortening page and validate

## Call Flow Routing

Customer calls → Google Voice (904-217-5152) → Forwarded to Twilio (904-834-9227) → Twilio processes/records → Forwarded to Personal Cell (904-663-4789)

This setup allows:

- Customer-facing number stays consistent (Google Voice)
- Twilio intercepts for recording, transcription, and CRM integration
- Calls still reach your personal cell for answering
- All call data gets captured in CRM automatically

## Business Requirements

For my mobile mechanic service at Mechanicstaugustine.com. I want to use caddy as the server. I want to use. rukoviditel. As the CRM. What I would like to happen? Is when I get a phone call at 904-217-5152. I would like. For it to automatically start recording. In the idea . The customer that's calling their information will get automatically ingested in the CRM. By transcribing the call and using ID tags like when I say your first name. first name will be an ID tag and whatever is said next. Will be what's ingested. So if they say Paul. Paul is what's ingested. And. It'll know to stop ingesting for that I'd when I say OK. So it'll go your first name. That'll be the trigger. Pole. OK and that's the other trigger. OK is the other trigger. And then that will be their first name. And then when I say last name. When I say. What's your last name? Last name will be a trigger for last name. They'll say Stevens. And when I say OK. OK will be the trigger. To let it know. That's the end of that. Ingestion. I would like it to capture their first name, Last name, Address, Type of car, Year, Make, Model and engine size. and speacial notes. Aslo when were done with the call save the call recording in their file in the crm. I use google vioce right now for my 904-217-5152 number but i also have twilio so that another option. I would also like on my website. The ability. For people or customers. To get a price on how much I would charge to fix their car automatically by filling in their information. So maybe if it has a catalog. Of. Times repair times. Then when customers can input their year, make and model and engine size of their car. And what it is that they need done? And then it will automatically give them a price based on that information.

## Repository Status

This repository currently has no discoverable code or documentation. As the project evolves, update this file to guide AI agents on architecture, workflows, and conventions.

## Guidance for AI Agents

- **Project Structure**: Document major components, service boundaries, and data flows as they emerge.
- **Developer Workflows**: List build, test, and debug commands that are not obvious from file inspection.
- **Conventions**: Note any project-specific patterns, naming conventions, or coding styles.
- **Integration Points**: Reference external dependencies and cross-component communication patterns.
- **Key Files**: As files are added, mention those that exemplify important patterns or contain critical logic.

## Example Section (to update as codebase grows)

```markdown
- Main entry point: `src/main.py`
- Service boundaries: `services/` directory contains microservices
- Build: Run `make build` from the project root
- Tests: All tests in `tests/`, run with `pytest`
- External API integration: See `src/integrations/`
```

## Overview

This workspace powers a local mechanic quoting app and a containerized Rukovoditel CRM. It combines a Go backend, a simple HTML/JS frontend, and a Dockerized PHP CRM stack.

## Architecture

- **Go Backend (`main.go`)**  
  - Exposes `/quote` (POST) for price estimates and `/twilio` (POST) for Twilio call webhooks.
  - Parses call transcriptions into structured leads, then posts to CRM via environment-configured API.
  - Uses in-memory repair catalog; update `catalog` for new repairs.
  - Environment variables control CRM integration (`CRM_API_URL`, `CRM_API_KEY`, etc.).

- **Frontend (`index.html`)**  
  - Simple form posts to `/quote` for instant price feedback.
  - No build step; static HTML/JS.

- **CRM Stack (`crm/`, `docker-compose.yml`)**  
  - MariaDB and PHP/Apache containers.
  - `crm-app` auto-downloads Rukovoditel on first run via `entrypoint.sh`.
  - PHP config tweaks in `php.ini` suppress installer warnings.
  - Caddy reverse proxy setup documented in `CRM.md`.

## Developer Workflows

- **Start Everything:**  
  `docker compose up -d --build` (from repo root)

S_CRM_ENABLED=1
WEERTS_CRM_API_URL=<https://weerts.us/crm/api/>
WEERTS_CRM_API_KEY=15589132311506514541131001388612707157206026129691454114279

Here’s how to finish HTTPS for weerts.us based on your latest outputs.

Checklist

- Ensure Caddy uses the main Caddyfile (not Caddyfile.http)
- Fix Caddyfile syntax (move email to global block)
- Fix log permissions
- Make 443 reachable publicly (and fix IPv6 AAAA if not using IPv6)

Do this

- Update Caddyfile (top of file):
  {
    email <sodjacksonville@gmail.com>
  }
  Remove the line “tls …” inside weerts.us.
- Fix log dir:
  sudo mkdir -p /var/log/caddy
  sudo chown -R www-data:www-data /var/log/caddy
- Point the service at the main Caddyfile and reload:
  sudo systemctl daemon-reload
  sudo systemctl restart weerts-caddy
  journalctl -u weerts-caddy -n 80 --no-pager
- Open/forward 443 on your router to this machine. Your HTTP works; HTTPS times out, so 443 isn’t reachable from the internet.
- If you have an AAAA record (you do), either:
  - Open IPv6 80/443 to this host, or
  - Temporarily remove the AAAA so ACME validates over IPv4.

Validate

- caddy validate --config /home/kylewee/code/weerts/Caddyfile
- curl -I <https://weerts.us/health>

## Execution Approach

- **Project Management**: Maintain a Kanban or backlog with clear acceptance criteria. Lock scope per milestone before development to avoid derailment.
- **Delivery Loop**: For each feature batch: refine requirements → update ERD/API schemas → implement backend → wire UI → seed or migrate data → write integration/unit tests → run end-to-end validation.
- **Testing & QA**: Adopt automated tests in Go (unit + integration), contract tests for API/client interactions, and Cypress/Playwright for UI. Stage environment mirrors production data shape.
- **Deployment**: Containerize the Go service, use CI/CD (GitHub Actions) for lint/test/build/deploy. Target phased rollout (shadow mode, limited users) before full cutover.
- **Security & Compliance**: Enforce hashed passwords (bcrypt/argon2), HTTPS everywhere, least-privilege roles, audit logging, and data backups. Review PII handling and retention.
- **Open Questions**: Payment provider choice, exact inventory granularity, offline/mobile requirements (caching), third-party data imports (labor guides), and CRM sunset timeline.

## Immediate Next Steps

1. Validate this blueprint with stakeholders and adjust scope if needed.
2. Elaborate the requirements doc (user stories, roles, success metrics).
3. Produce the ERD and migration plan for the new database.
4. Scaffold the Go service repository/module and set up CI basics.
5. Plan the quote intake handoff (PHP → API) as the first integration point.
