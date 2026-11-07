<?php

namespace App\Helper;

use ModelHelper;
use Image;

class Upload
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'shtml', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'ksh', 'cmd',
        'bat', 'exe', 'dll', 'so', 'jsp', 'asp', 'aspx', 'cer', 'asa', 'asax',
        'config', 'htaccess',
    ];

    private static function safeExtension($file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: 'bin';

        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('This file type is not allowed.');
        }

        return $ext;
    }

    public static function fileUpload($file, $path = "")
    {
        $rootPath = base_path('../uploads');

        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $ext = self::safeExtension($file);
        $image = uniqid() . '.' . $ext;
        $file->move($fullPath, $image);

        return 'uploads/' . $path . '/' . $image;
    }

    public static function uploadData($file, $path = "")
    {
        $rootPath = base_path('../uploads');
        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $ext = self::safeExtension($file);
        $image = uniqid() . '.' . $ext;
        $file->move($fullPath, $image);

        return 'uploads/' . $path . '/' . $image;
    }

    public static function uploadWithLogoImageData($file, $path = "")
    {
        $rootPath = base_path('../uploads');
        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $ext = self::safeExtension($file);
        $image = uniqid() . '.' . $ext;
        $file->move($fullPath, $image);

        return 'uploads/' . $path . '/' . $image;
    }

    public static function logouploadData($file, $path = "")
    {
        $rootPath = base_path('../uploads');
        $cleanPath = trim($path, '/');
        $fullPath = $cleanPath ? $rootPath . DIRECTORY_SEPARATOR . $cleanPath : $rootPath;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $ext = self::safeExtension($file);
        $image = "logo-" . uniqid() . '.' . $ext;
        $file->move($fullPath, $image);

        $finalPath = 'uploads' . ($cleanPath ? '/' . $cleanPath : '') . '/' . $image;

        return $finalPath;
    }

    public static function videoUploadData($file, $path = "")
    {
        $rootPath = base_path('../uploads');
        $cleanPath = trim($path, '/');
        $fullPath = $cleanPath ? $rootPath . DIRECTORY_SEPARATOR . $cleanPath : $rootPath;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $ext = self::safeExtension($file);
        $image = "video-" . uniqid() . '.' . $ext;
        $file->move($fullPath, $image);

        $finalPath = 'uploads' . ($cleanPath ? '/' . $cleanPath : '') . '/' . $image;

        return $finalPath;
    }
}
