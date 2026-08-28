<?php

namespace App\Domains\Voicemail\Enums;

enum VoicemailMessageFolder: string
{
    case New = 'new';
    case Saved = 'saved';
    case Deleted = 'deleted';
}
