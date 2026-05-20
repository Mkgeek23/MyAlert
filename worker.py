#!/usr/bin/env python3
"""
MyAlert Worker - Python Cron Script

Fetches due alerts from the PHP API endpoint and sends them to Discord webhooks.
Reports delivery results back to the API for state management.

Usage:
    python worker.py

Configuration:
    Set environment variables or edit the CONFIG section below.
    - MYALERT_API_URL: Base URL of the MyAlert API (e.g. http://localhost/php/MyAlert/public)
    - MYALERT_API_KEY: The worker_api_key from config/app.php

Cron example (every minute):
    * * * * * /usr/bin/python3 /path/to/MyAlert/worker.py
"""

import json
import logging
import os
import sys
import time
from datetime import date
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

# ─── Configuration ────────────────────────────────────────────────────────────

API_URL = os.environ.get("MYALERT_API_URL", "http://localhost/php/MyAlert/public")
API_KEY = os.environ.get("MYALERT_API_KEY", "TestHehe")

# Discord rate limit: wait this many seconds between webhook sends
SEND_DELAY = 0.5

# Request timeout in seconds
TIMEOUT = 15

# ─── Logging ──────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)
logger = logging.getLogger("myalert-worker")


# ─── Helper Functions ─────────────────────────────────────────────────────────


def api_request(endpoint: str, method: str = "GET", data: dict | None = None) -> dict:
    """Make an authenticated request to the MyAlert API."""
    url = f"{API_URL.rstrip('/')}/{endpoint}"
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json",
    }

    body = json.dumps(data).encode("utf-8") if data else None
    req = Request(url, data=body, headers=headers, method=method)

    try:
        with urlopen(req, timeout=TIMEOUT) as response:
            return json.loads(response.read().decode("utf-8"))
    except HTTPError as e:
        error_body = e.read().decode("utf-8", errors="replace")
        logger.error(f"API error {e.code}: {error_body}")
        raise
    except URLError as e:
        logger.error(f"API connection error: {e.reason}")
        raise


def get_embed_color(next_run_at: str) -> int:
    """Determine embed color based on alert's next_run_at date."""
    try:
        alert_date = next_run_at[:10]  # Extract YYYY-MM-DD
        today = date.today().isoformat()

        if alert_date > today:
            return 0x00FF00  # Green - future
        elif alert_date == today:
            return 0xFFFF00  # Yellow - today
        else:
            return 0xFF0000  # Red - past/overdue
    except (ValueError, IndexError):
        return 0xFF0000  # Default to red on parse error


def build_discord_payload(alert: dict) -> dict:
    """Build the Discord webhook JSON payload for an alert."""
    base_url = API_URL.rstrip("/")
    close_link = f"{base_url}/close-alert?token={alert['close_token']}"

    embed = {
        "title": alert["title"],
        "color": get_embed_color(alert["next_run_at"]),
        "fields": [
            {
                "name": "Close Alert",
                "value": f"[Click here to close]({close_link})",
                "inline": False,
            }
        ],
    }

    if alert.get("description"):
        embed["description"] = alert["description"]

    return {"embeds": [embed]}


def send_to_discord(webhook_url: str, payload: dict) -> dict:
    """Send a payload to a Discord webhook URL. Returns result dict."""
    body = json.dumps(payload).encode("utf-8")
    headers = {
        "Content-Type": "application/json",
        "User-Agent": "MyAlert-Worker/1.0 (Python)",
    }
    req = Request(webhook_url, data=body, headers=headers, method="POST")

    try:
        with urlopen(req, timeout=TIMEOUT) as response:
            response_body = response.read().decode("utf-8", errors="replace")
            return {
                "success": True,
                "status_code": response.status,
                "body": response_body,
            }
    except HTTPError as e:
        error_body = e.read().decode("utf-8", errors="replace")
        return {
            "success": False,
            "status_code": e.code,
            "body": error_body,
            "error": str(e),
        }
    except URLError as e:
        return {
            "success": False,
            "status_code": 0,
            "body": "",
            "error": f"Connection error: {e.reason}",
        }
    except Exception as e:
        return {
            "success": False,
            "status_code": 0,
            "body": "",
            "error": str(e),
        }


# ─── Main Worker Logic ────────────────────────────────────────────────────────


def main() -> int:
    """Main worker entry point. Returns exit code (0=success, 1=error)."""
    if not API_KEY:
        logger.error("MYALERT_API_KEY is not set. Set it as an environment variable.")
        return 1

    logger.info("Worker cycle started")

    # Step 1: Fetch due alerts from the API
    try:
        response = api_request("api-worker?action=fetch")
    except Exception:
        logger.error("Failed to fetch alerts from API")
        return 1

    alerts = response.get("alerts", [])
    count = response.get("count", 0)

    if count == 0:
        logger.info("No due alerts to process")
        return 0

    logger.info(f"Fetched {count} alert(s) to send")

    # Step 2: Send each alert to Discord
    results = []

    for alert in alerts:
        alert_id = alert["id"]
        webhook_url = alert["webhook_url"]

        logger.info(f"Sending alert #{alert_id} to Discord...")

        payload = build_discord_payload(alert)
        result = send_to_discord(webhook_url, payload)

        report_item = {
            "alert_id": alert_id,
            "success": result["success"],
            "status_code": result["status_code"],
            "body": result["body"][:500],  # Truncate long responses
        }

        if not result["success"]:
            report_item["error"] = result.get("error", "Unknown error")
            logger.error(
                f"Alert #{alert_id} failed: HTTP {result['status_code']} - {result.get('error', '')}"
            )
        else:
            logger.info(f"Alert #{alert_id} sent successfully (HTTP {result['status_code']})")

        results.append(report_item)

        # Rate limit protection
        time.sleep(SEND_DELAY)

    # Step 3: Report results back to the API
    logger.info(f"Reporting {len(results)} result(s) to API...")

    try:
        report_response = api_request(
            "api-worker?action=report", method="POST", data={"results": results}
        )
        processed = report_response.get("processed", 0)
        errors = report_response.get("errors", [])

        logger.info(f"Report accepted: {processed} processed")
        if errors:
            for err in errors:
                logger.warning(f"Report error: {err}")
    except Exception:
        logger.error("Failed to report results to API")
        return 1

    # Summary
    success_count = sum(1 for r in results if r["success"])
    fail_count = len(results) - success_count
    logger.info(
        f"Worker cycle completed: {count} fetched, {success_count} succeeded, {fail_count} failed"
    )

    return 0


if __name__ == "__main__":
    sys.exit(main())
