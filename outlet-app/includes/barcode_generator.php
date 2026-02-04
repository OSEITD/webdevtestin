<?php
class BarcodeGenerator {
    private $barcodeApiUrl;
    private $localStoragePath;
    private $supabaseHelper;

    public function __construct($supabaseHelper = null) {
        $this->barcodeApiUrl = 'https://barcode.tec-it.com/barcode.ashx';
        $this->localStoragePath = __DIR__ . '/../assets/barcodes/';
        $this->supabaseHelper = $supabaseHelper;

        if (!is_dir($this->localStoragePath)) {
            mkdir($this->localStoragePath, 0755, true);
        }
    }

    public function generateBarcode($trackingNumber, $format = 'Code128', $options = []) {
        try {
            $defaultOptions = [
                'data' => $trackingNumber,
                'code' => $format,
                'multiplebarcodes' => 'false',
                'translate-esc' => 'false',
                'unit' => 'Fit',
                'dpi' => '96',
                'imagetype' => 'Png',
                'rotation' => '0',
                'color' => '000000',
                'bgcolor' => 'FFFFFF',
                'qunit' => 'Mm',
                'quiet' => '0',
                'modulewidth' => '0.3',
                'height' => '15'
            ];

            $options = array_merge($defaultOptions, $options);

            $barcodeUrl = $this->generateWithOnlineAPI($trackingNumber, $options);
            if ($barcodeUrl) {
                return [
                    'success' => true,
                    'barcode_url' => $barcodeUrl,
                    'method' => 'online_api'
                ];
            }

            $localUrl = $this->generateLocally($trackingNumber, $options);
            if ($localUrl) {
                return [
                    'success' => true,
                    'barcode_url' => $localUrl,
                    'method' => 'local_generation'
                ];
            }

            $fallbackUrl = $this->generateFallback($trackingNumber);
            return [
                'success' => true,
                'barcode_url' => $fallbackUrl,
                'method' => 'fallback',
                'warning' => 'Using fallback barcode generation'
            ];

        } catch (Exception $e) {
            error_log("Barcode generation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate barcode: ' . $e->getMessage()
            ];
        }
    }

    private function generateWithOnlineAPI($trackingNumber, $options) {
        try {
            $queryParams = http_build_query($options);
            $apiUrl = $this->barcodeApiUrl . '?' . $queryParams;

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'WDParcel-BarcodeGenerator/1.0'
                ]
            ]);

            $barcodeData = @file_get_contents($apiUrl, false, $context);

            if ($barcodeData === false) {
                return null;
            }

            $filename = 'barcode_' . $trackingNumber . '_' . time() . '.png';
            $filepath = $this->localStoragePath . $filename;

            if (file_put_contents($filepath, $barcodeData)) {
                // Return a relative web path to the barcode so it resolves from pages in the outlet app
                return '../assets/barcodes/' . $filename;
            }

            return null;
        } catch (Exception $e) {
            error_log("Online barcode generation failed: " . $e->getMessage());
            return null;
        }
    }

    private function generateLocally($trackingNumber, $options) {
        if (!extension_loaded('gd')) {
            return null;
        }

        try {
            $width = 300;
            $height = 80;
            $image = imagecreate($width, $height);

            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);

            imagefill($image, 0, 0, $white);

            $this->drawBarcodePattern($image, $trackingNumber, $black, $width, $height);

            $font_size = 3;
            $text_width = imagefontwidth($font_size) * strlen($trackingNumber);
            $text_x = ($width - $text_width) / 2;
            $text_y = $height - 20;
            imagestring($image, $font_size, $text_x, $text_y, $trackingNumber, $black);

            $filename = 'barcode_local_' . $trackingNumber . '_' . time() . '.png';
            $filepath = $this->localStoragePath . $filename;

            if (imagepng($image, $filepath)) {
                imagedestroy($image);
                // Return a relative web path to the locally generated barcode
                return '../assets/barcodes/' . $filename;
            }

            imagedestroy($image);
            return null;
        } catch (Exception $e) {
            error_log("Local barcode generation failed: " . $e->getMessage());
            return null;
        }
    }

    private function drawBarcodePattern($image, $data, $color, $width, $height) {
        $barcode_height = $height - 30;
        $bar_width = 2;

        $hash = md5($data);
        $x = 20;

        for ($i = 0; $i < min(strlen($hash), 50); $i++) {
            $char = $hash[$i];
            $decimal = hexdec($char);

            if ($decimal % 2 == 0) {
                imagefilledrectangle($image, $x, 10, $x + $bar_width, $barcode_height, $color);
            }
            $x += $bar_width + 1;

            if ($x >= $width - 20) break;
        }
    }

    private function generateFallback($trackingNumber) {
        try {
            $content = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Courier New', monospace; margin: 20px; text-align: center; }
        .barcode {
            font-size: 24px;
            letter-spacing: 5px;
            border: 2px solid #000;
            padding: 10px;
            display: inline-block;
            background: white;
            margin-bottom: 10px;
        }
        .tracking { font-size: 14px; margin-top: 10px; }
        .bars {
            font-family: Arial;
            font-size: 32px;
            line-height: 0.8;
            letter-spacing: 1px;
            color: #000;
        }
    </style>
</head>
<body>
    <div class='bars'>||||| || ||| || ||||| | ||| |||| | || |||||</div>
    <div class='barcode'>{$trackingNumber}</div>
    <div class='tracking'>Tracking Number: {$trackingNumber}</div>
</body>
</html>";

            $filename = 'barcode_fallback_' . $trackingNumber . '.html';
            $filepath = $this->localStoragePath . $filename;

            if (file_put_contents($filepath, $content)) {
                // Return a relative web path to the fallback barcode HTML
                return '../assets/barcodes/' . $filename;
            }

            return null;
        } catch (Exception $e) {
            error_log("Fallback barcode generation failed: " . $e->getMessage());
            return null;
        }
    }

    public function cleanupOldBarcodes($daysOld = 30) {
        $files = glob($this->localStoragePath . 'barcode_*');
        $cutoff = time() - ($daysOld * 24 * 60 * 60);

        $cleaned = 0;
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}
?>
