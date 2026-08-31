<?php

namespace App\Domains\GlobalSearch\Enums;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Database\Eloquent\Model;

enum GlobalSearchType: string
{
    case Extension = 'extension';
    case Device = 'device';
    case PhoneNumber = 'phone_number';
    case Callflow = 'callflow';
    case VoicemailBox = 'voicemail_box';
    case Queue = 'queue';
    case Menu = 'menu';
    case Conference = 'conference';
    case Directory = 'directory';
    case Group = 'group';
    case Media = 'media';
    case Recording = 'recording';
    case FaxBox = 'fax_box';
    case Blacklist = 'blacklist';
    case CallerIdList = 'caller_id_list';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Extension => SwitchExtension::class,
            self::Device => SwitchDevice::class,
            self::PhoneNumber => SwitchPhoneNumber::class,
            self::Callflow => SwitchCallflow::class,
            self::VoicemailBox => SwitchVoicemailBox::class,
            self::Queue => SwitchQueue::class,
            self::Menu => SwitchMenu::class,
            self::Conference => SwitchConference::class,
            self::Directory => SwitchDirectory::class,
            self::Group => SwitchGroup::class,
            self::Media => SwitchMedia::class,
            self::Recording => SwitchRecording::class,
            self::FaxBox => SwitchFaxBox::class,
            self::Blacklist => SwitchBlacklist::class,
            self::CallerIdList => SwitchCallerIdList::class,
        };
    }

    public function groupLabel(): string
    {
        return match ($this) {
            self::Extension => 'People & Extensions',
            self::Device => 'Devices',
            self::PhoneNumber => 'Phone Numbers',
            self::Callflow => 'Call Routing',
            self::VoicemailBox => 'Voicemail',
            self::Queue => 'Queues & Agents',
            self::Menu => 'Menus & IVR',
            self::Conference => 'Conferences',
            self::Directory => 'Directories',
            self::Group => 'Groups & Ring Groups',
            self::Media => 'Media & Music on Hold',
            self::Recording => 'Recordings',
            self::FaxBox => 'Fax Boxes',
            self::Blacklist => 'Blacklists',
            self::CallerIdList => 'Caller-ID Lists',
        };
    }
}
