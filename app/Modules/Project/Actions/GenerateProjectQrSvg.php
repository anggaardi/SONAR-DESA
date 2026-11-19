<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class GenerateProjectQrSvg
{
    public function handle(Project $project): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
        ]);

        $url = url('/p/'.$project->slug);

        return (new QRCode($options))->render($url);
    }
}
