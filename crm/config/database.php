<?php

// define database connection using environment variables
// Set these in your environment or .env file (not committed to git)
  define('DB_SERVER', getenv('CRM_DB_HOST') ?: 'localhost');
  define('DB_SERVER_USERNAME', getenv('CRM_DB_USER') ?: 'kylewee');
  define('DB_SERVER_PASSWORD', getenv('CRM_DB_PASSWORD') ?: '');
  define('DB_SERVER_PORT', getenv('CRM_DB_PORT') ?: '');
  define('DB_DATABASE', getenv('CRM_DB_NAME') ?: 'rukovoditel');  	  
  