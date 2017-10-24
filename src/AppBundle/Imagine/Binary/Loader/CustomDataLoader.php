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

        try {
            $binary = file_get_contents($path);
        } catch (\Exception $e) {
            $binary = file_get_contents(ImagineController::IMAGES_PATH . ImagineController::DEFAULT_IMAGE_FILE);
        }

        $mime = $this->mimeTypeGuesser->guess($binary);

        if ($mime === 'text/plain' && substr($ext, 0, 3) === 'svg') {
            $mime = 'image/svg';
        }

        return new Binary($binary, $mime, $ext);
    }
}
