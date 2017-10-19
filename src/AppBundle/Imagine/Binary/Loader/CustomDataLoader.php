<?php

namespace AppBundle\Imagine\Binary\Loader;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Model\Binary;
use Liip\ImagineBundle\Binary\Locator\LocatorInterface;
use Liip\ImagineBundle\Binary\MimeTypeGuesserInterface;
use AppBundle\Imagine\Controller\ImagineController;

class CustomDataLoader implements LoaderInterface
{
    /**
     * @var MimeTypeGuesserInterface
     */
    protected $mimeTypeGuesser;

    /**
     * @var LocatorInterface
     */
    protected $locator;

    public function __construct(MimeTypeGuesserInterface $mimeGuesser, LocatorInterface $locator)
    {
        $this->mimeTypeGuesser = $mimeGuesser;
        $this->locator = $locator;
    }

    /**
     * @param mixed $path
     *
     * @return BinaryInterface
     */
    public function find($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $url = urldecode($path);
        $fileName = preg_replace("/[^a-zA-Z0-9\\-\\_\\/\\.]+/", "", $path);
        $path = ImagineController::IMAGES_PATH . $fileName;
        try {
            $localPath = $this->locator->locate($path);
            $binary = file_get_contents($localPath);

        } catch (\Exception $e) {
            $localPath = $path;
            try {
                if ($content = file_get_contents($url)) {
                    $fp = fopen($localPath, "wb");
                    fwrite($fp, $content);
                    fclose($fp);
                    $binary = $content;
                } else {
                    $binary = file_get_contents(ImagineController::IMAGES_PATH . ImagineController::DEFAULT_IMAGE_FILE);
                }

            } catch (\Exception $exception) {
                $binary = file_get_contents(ImagineController::IMAGES_PATH . ImagineController::DEFAULT_IMAGE_FILE);
            }
        }

        $mime = $this->mimeTypeGuesser->guess($binary);

        return new Binary($binary, $mime, $ext);
    }
}
