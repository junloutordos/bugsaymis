<?php

// AtlasGo mobile app — direct-download distribution while the Play Store
// listing is pending verification. Bump `mobile_version` each time a new
// APK is uploaded to S3 at `apk_s3_key` (see project docs / memory for the
// upload step — it's a manual, confirmed-per-upload action, not automated).
return [
    'mobile_version' => '1.2.0',
    'apk_s3_key'      => 'atlas-app-releases/atlasgo-latest.apk',
];
