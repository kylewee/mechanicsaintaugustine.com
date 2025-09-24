<?php

// Twilio Configuration for Voice System  
const TWILIO_FORWARD_TO = '+19046634789';  // Your personal phone
const TWILIO_ACCOUNT_SID = 'REDACTED_TWILIO_SID';  // Your Twilio Account SID
const TWILIO_AUTH_TOKEN = 'REDACTED_TWILIO_TOKEN';
const TWILIO_SMS_FROM = 'REDACTED_TWILIO_FROM';
const CRM_API_URL = 'https://mechanicstaugustine.com/crm/api/rest.php';
const CRM_API_KEY = 'REDACTED_CRM_API_KEY';

// Set to your actual Leads Entity ID
const CRM_LEADS_ENTITY_ID = 26;
const CRM_USERNAME = 'kylewee2'; 
const CRM_PASSWORD = 'NewPass123!';
// Minimal mapping to get Leads created now with First/Last split.
// We also map 'name' to First Name as a fallback so it "just works".
const CRM_FIELD_MAP = [
  'name'        => 0,    // replace 0s with your real IDs if you have a combined name
  'first_name'  => 219,  // keep if correct
  'last_name'   => 220,  // keep if correct
  'phone'       => 227,  // mapped to Phone
  'address'     => 234,  // mapped to Address
  'year'        => 231,  // mapped to year
  'make'        => 232,  // mapped to Make
  'model'       => 233,  // mapped to model
  'engine_size' => 0,
  'notes'       => 230,  // mapped to notes (textarea_wysiwyg)
];

define('CRM_CREATED_BY_USER_ID', 1); // change to kylewee2's user ID when known

// OpenAI configuration (for Whisper transcription and AI extraction)
const OPENAI_API_KEY = 'REDACTED_OPENAI_KEY';

// Optional: Secure the recordings page with a tokenized link. Leave empty to disable.
// Example usage: https://mechanicstaugustine.com/voice/recording_callback.php?action=recordings&token=YOUR_TOKEN
const VOICE_RECORDINGS_TOKEN = 'msarec-' . '2b7c9f1a5d4e';

// Native Twilio Transcription (simpler than CI)
// Automatically transcribes recordings up to 2 minutes using <Record transcribe="true">
// Transcripts are delivered to recordingStatusCallback with TranscriptionText field
const TWILIO_TRANSCRIBE_ENABLED = true;  // Enable auto-transcription for recordings
