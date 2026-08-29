<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

enum VoicemailMessageFolder: string
{
    case New = 'new';
    case Saved = 'saved';
    case Deleted = 'deleted';
}
