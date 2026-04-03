<?php

declare(strict_types=1);

namespace App\Helpers;

class DiscordConstants
{
    // Interaction Types
    public const TYPE_PING = 1;
    public const TYPE_APPLICATION_COMMAND = 2;
    public const TYPE_MESSAGE_COMPONENT = 3;
    public const TYPE_APPLICATION_COMMAND_AUTOCOMPLETE = 4;
    public const TYPE_MODAL_SUBMIT = 5;

    // Interaction Response Types
    public const RESPONSE_PONG = 1;
    public const RESPONSE_CHANNEL_MESSAGE = 4;
    public const RESPONSE_DEFERRED_CHANNEL_MESSAGE = 5;
    public const RESPONSE_DEFERRED_UPDATE_MESSAGE = 6;
    public const RESPONSE_UPDATE_MESSAGE = 7;
    public const RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT = 8;
    public const RESPONSE_MODAL = 9;

    // Message Flags
    public const FLAG_EPHEMERAL = 64;
}
