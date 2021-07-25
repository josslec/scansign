<?php

/*
 * Dependances :
 * - php-cli
 * - composer
 * - ghostscript
 * - convert
 * (- pdftk)
 *
 */


$document = $argv[1];
$signature = posix_getpwuid(posix_getuid())['dir'].'/.scansign/signature.png';


// Normal autoload
$autoload = __DIR__ . '/../php-gui/vendor/autoload.php';

require $autoload;

use Gui\Application;
use Gui\Components\Image;
use Gui\Components\Button;


$tmpdir = trim(shell_exec('mktemp -d --t scansign-XXXXXXXXXX'));
$document_bn = basename($document, '.pdf');

//shell_exec('sudo sed -i_bak 's/rights="none" pattern="PDF"/rights="read | write" pattern="PDF"/' /etc/ImageMagick-*/policy.xml'); // Que si droits non accordés sur PDF

shell_exec('gs -sDEVICE=pdfwrite -dBATCH -dNOPAUSE -dCompatibilityLevel=1.4 -dColorConversionStrategy=/sRGB -dProcessColorModel=/DeviceRGB -dUseCIEColor=true -sOutputFile="'.$tmpdir.'/'.$document_bn.'_RGB.pdf" "'.$document.'"');
//shell_exec('pdftk "'.$tmpdir.'/'.$document_bn.'_RGB.pdf" burst output "'.$tmpdir.'/'.$document_bn.'-%04d.pdf"');

shell_exec('convert -density "150" "'.$tmpdir.'/'.$document_bn.'_RGB.pdf" -resize 1240x1754! "'.$tmpdir.'/'.$document_bn.'_RGB.png"');

// miniature
shell_exec('convert -flatten -resize 496x700! "'.$tmpdir.'/'.$document_bn.'_RGB.png" "'.$tmpdir.'/'.$document_bn.'_RGB_white.png"');



$app = new Application([
    'title' => 'ScanSign',
    'width' => 496,
    'height' => 700,
    'icon' => realpath(__DIR__) . DIRECTORY_SEPARATOR . 'php.ico'
]);

$app->on('start', function () use ($app) {

	global $tmpdir, $document_bn;
    $file = $tmpdir.'/'.$document_bn.'_RGB_white.png';
    $imgw = 496;
    $imgh = 700;

    $image = new Image([]);
    $image->setFile($file);

	$button = [];
	$selected_button = '';
	
	$i = 0;
	while ($i < 150) {

		$button[$i] = new Button([
	        'value' => $i,
	        'top' => intval(700/15)*intval($i/10),
	        'left' => intval(496/10)*($i%10),
	        'width' => 30
    	]);

    	eval('$button['.$i.']->on(\'click\', function () { global $selected_button, $app; $selected_button = '.$i.'; $app->terminate(); });');

		$i++;
	}

});

$app->run();

echo $selected_button;
$sig_x = intval(1240/10)*($selected_button%10);
$sig_y = intval(1754/15)*intval($selected_button/10);



shell_exec('convert "'.$tmpdir.'/'.$document_bn.'_RGB.png" "'.$signature.'" -geometry "+'.$sig_x.'+'.$sig_y.'" +profile \'*\' -composite "'.$tmpdir.'/'.$document_bn.'-signed.png"');

shell_exec('convert -density "150" "'.$tmpdir.'/'.$document_bn.'-signed.png" -attenuate 0.25 "'.$tmpdir.'/'.$document_bn.'-scanned.pdf"');

shell_exec('gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/default -dNOPAUSE -dQUIET -dBATCH -dDetectDuplicateImages -dCompressFonts=true -r"150" -sOutputFile="'.dirname($document).'/'.$document_bn.'-signe.pdf" "'.$tmpdir.'/'.$document_bn.'-scanned.pdf"');


shell_exec('rm -f '.$tmpdir.'/*');
shell_exec('rm -Rf '.$tmpdir);
