<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a long-running importer when a cancellation has been requested.
 */
class ImportCancelledException extends RuntimeException {}
