<?php

namespace App\Ems\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for the EMS module.
 *
 * EMS controllers are thin by contract: validate through a FormRequest,
 * authorize through a Policy, delegate to a Service, respond through
 * ApiResponse. No query building and no business rules live in this layer.
 */
abstract class EmsController extends BaseController
{
    use AuthorizesRequests;
}
