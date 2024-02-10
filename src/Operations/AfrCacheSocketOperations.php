<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Operations;

class AfrCacheSocketOperations
{
    //operations
    public const OP_GET = 1;
    public const OP_SET = 2;
    public const OP_DELETE = 3;
    public const OP_FLUSH = 4;
    public const OP_INCREMENT = 4;
    public const OP_DECREMENT = 4;
    public const OP_HAS = 444;//isset
    public const OP_EXISTS = 444;//isset
    public const OP_GET_ALL_KEYS = 5;
    public const OP_COUNT_KEYS = 6;
    public const OP_GET_SERVER_STATS = 7;
/*
    public const OP_GET_MEMORY_USAGE = 10;
    public const OP_GET_MEMORY_PEAK_USAGE = 11;
    public const OP_GET_MEMORY_LIMIT = 12;
    public const OP_GET_MEMORY_AVAILABLE_PERCENT = 13;
    public const OP_GET_START_TIME = 14;
    public const OP_GET_TOTAL_SOCKET_CONNECTION_COUNT = 15;
*/
    public const OP_SET_REBOOT = 21;
    public const OP_SET_SHUTDOWN = 22;

    public const OP_GET_TOP_50_VALUE_SIZES = 31;
    public const OP_GET_TOP_200_VALUE_SIZES = 32;
    public const OP_GET_TOP_1000_VALUE_SIZES = 33;

    //where
    public const WH_KEYS_EQUAL = 1;
    public const WH_KEYS_CONTAINING = 2; //%LIKE% strpos !== false
    public const WH_KEYS_NOT_CONTAINING = 3; //%LIKE% strpos !== false
    public const WH_KEYS_STARTING = 4; //LIKE%
    public const WH_KEYS_ENDING = 5; //%LIKE
    public const WH_KEYS_REGEX = 6;
    public const WH_KEYS_ALL = 7;//ALL KEYS + FLUSH ONLY

    public const WH_VALUE_EQUAL = 11;
    public const WH_VALUE_CONTAINING = 12; //%LIKE% strpos !== false

    //what to return
    public const RETURN_VALUES = 1;
    public const RETURN_KEYS = 2;
    public const RETURN_BOOL_SUCCESS_OR_EXISTENCE = 3;

}