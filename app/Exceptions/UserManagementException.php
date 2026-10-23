<?php

namespace App\Exceptions;

use RuntimeException;

/** A user change was refused. The message is safe to show to the owner. */
class UserManagementException extends RuntimeException {}
