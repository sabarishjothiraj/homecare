<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payroll action refused for a business reason; the message is shown to the user.
 */
class PayrollException extends RuntimeException {}
