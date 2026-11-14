<?php

return [
    // Off by default: institutions issue accounts through a reviewed onboarding process.
    'self_registration' => (bool) env('EDVORA_SELF_REGISTRATION', false),
    'version' => '0.0.1',
];
