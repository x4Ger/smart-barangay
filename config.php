<?php
// Change these to match your MySQL setup.
// XAMPP default: user "root", empty password.
const DB_HOST = 'localhost';
const DB_NAME = 'smart_barangay';
const DB_USER = 'root';
const DB_PASS = '';

// ---- EcoBot (AI assistant) settings ----
// Get a key at https://console.anthropic.com (API usage is billed separately from a claude.ai chat plan).
// Keep this key private. Never put it in index.html.
const ANTHROPIC_API_KEY = 'sk-ant-api03-xxxxxxxxxxxxxxxxxxxx';
const AI_MODEL = 'claude-haiku-5-5';   // fast and low cost
const AI_LIMIT_PER_HOUR = 20;          // max chat messages per visitor per hour (protects your bill)
const CURL_VERIFY_SSL = false;          // if XAMPP shows "SSL certificate problem", set false for LOCAL testing only
