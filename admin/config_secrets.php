<?php
return [
  // Database
  'DB_HOST'    => 'localhost',
  'DB_USER'    => 'root',
  'DB_PASS'    => '',
  'DB_NAME'    => 'barbs_bali_booking',

  // Square (sandbox by default)
  'SQUARE_APPLICATION_ID' => 'sandbox-sq0idb-_qiYzv__WUfvY8Zmxn-Srw',
  'SQUARE_ACCESS_TOKEN'   => 'EAAAlznsxg7GGSO8lJeTG50Agr_fcMQbQGM3vzV1eo20y4k-cVQcCV1I0DrU4vNA',
  'SQUARE_LOCATION_ID'    => 'LXH9S1ZFQ32WE',
  'SQUARE_MCC'            => '7299',

  // PayPal
  'PAYPAL_CLIENT_ID'      => 'ATGD6YEDIugvmDtsUAB77IMG-aalxulLt1awBPQ7Ry5fsYg-fLRtu5l8z8htyJD5ECrR8UPlArHdP0Ix',

  // SMTP
  'SMTP_HOST'      => 'mail.barbsbaliapartments.com',
  'SMTP_PORT'      => 587,
  'SMTP_USERNAME'  => 'bookings@barbsbaliapartments.com',
  'SMTP_PASSWORD'  => 'T@kesbundy2bH@ppy',
  'SMTP_FROM_EMAIL'=> 'bookings@barbsbaliapartments.com',
  'SMTP_FROM_NAME' => 'Barbs Bali Apartments',

  // Housekeeping / Admin
  'HOUSEKEEPING_EMAIL' => 'housekeeping@barbsbaliapartments.com',
  'ADMIN_USER'         => 'admin',
  'ADMIN_PASS'         => 'letmein' // Replace in production
];
