<?php

return [
    'ffmpeg_binary' => env('MEDIA_FFMPEG_BIN', 'ffmpeg'),
    'ffprobe_binary' => env('MEDIA_FFPROBE_BIN', 'ffprobe'),

    'wall_video' => [
        'enabled' => env('WALL_VIDEO_ENABLED', true),
        'max_duration_seconds' => (int) env('WALL_VIDEO_MAX_DURATION_SECONDS', 30),
        'default_playback_mode' => env('WALL_VIDEO_DEFAULT_PLAYBACK_MODE', 'play_to_end_if_short_else_cap'),
        'default_resume_mode' => env('WALL_VIDEO_DEFAULT_RESUME_MODE', 'resume_if_same_item_else_restart'),
        'default_audio_policy' => env('WALL_VIDEO_DEFAULT_AUDIO_POLICY', 'muted'),
        'default_multi_layout_policy' => env('WALL_VIDEO_DEFAULT_MULTI_LAYOUT_POLICY', 'disallow'),
        'default_preferred_variant' => env('WALL_VIDEO_DEFAULT_PREFERRED_VARIANT', 'wall_video_720p'),
    ],

    'public_upload' => [
        'video_enabled' => env('PUBLIC_UPLOAD_VIDEO_ENABLED', true),
        'video_single_only' => true,
        'video_max_duration_seconds' => (int) env(
            'PUBLIC_UPLOAD_VIDEO_MAX_DURATION_SECONDS',
            env('WALL_VIDEO_MAX_DURATION_SECONDS', 30),
        ),
        'accept_hint' => env('PUBLIC_UPLOAD_ACCEPT_HINT', 'image/*,video/mp4,video/quicktime'),
    ],
];
