<?php

namespace App\Http\Requests\Academy;

use App\Http\Requests\CMS\UploadAssetRequest;

/**
 * Image-only upload for Academy/DAMS course assets.
 * Reuses CMS image validation rules without using CMS ownership or media library.
 */
class UploadAcademyAssetRequest extends UploadAssetRequest
{
}
