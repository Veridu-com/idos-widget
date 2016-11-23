<?php

return [
    'amazon' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'profile', 'postal_code'
        ]
    ],
    'dropbox' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => []
    ],
    'facebook' => [
        'sso'    => true,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'email', 'public_profile', 'user_friends'
        ],
        'options' => [
            'display' => 'popup'
        ],
        'version' => '2.0'
    ],
    'google' => [
        'sso'    => true,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'openid', 'profile', 'email',
            'https://www.googleapis.com/auth/plus.me',
            'https://www.googleapis.com/auth/plus.login',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.google.com/m8/feeds/',
            'https://www.googleapis.com/auth/drive.apps.readonly',
            'https://www.googleapis.com/auth/drive.metadata.readonly',
            'https://www.googleapis.com/auth/drive.readonly'
        ]
    ],
    'instagram' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'basic'
        ]
    ],
    'linkedin' => [
        'sso'    => true,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'r_basicprofile', 'r_emailaddress'
        ]
    ],
    'paypal' => [
        'sso'    => true,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'https://uri.paypal.com/services/paypalattributes',
            'profile', 'email', 'address', 'phone', 'openid'
        ]
    ],
    'spotify' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***',
        'scope'  => [
            'playlist-read-private', 'playlist-read-collaborative', 'user-follow-read',
            'user-library-read', 'user-read-private', 'user-read-birthdate', 'user-read-email'
        ]
    ],
    'twitter' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***'
    ],
    'yahoo' => [
        'sso'    => false,
        'key'    => '***REMOVED***',
        'secret' => '***REMOVED***'
    ]
];
