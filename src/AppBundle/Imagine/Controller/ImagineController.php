<?php

namespace AppBundle\Imagine\Controller;

use Liip\ImagineBundle\Controller\ImagineController as DefaultImagineController;
use Imagine\Exception\RuntimeException;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImagineController extends DefaultImagineController
{
    const IMAGES_PATH = 'uploads/links/';
    const DEFAULT_IMAGE_FILE = 'default-content-image-squared.jpg';
    /**
     * This action applies a given filter to a given image, optionally saves the image and outputs it to the browser at the same time.
     *
     * @param Request $request
     * @param string  $path
     * @param string  $filter
     *
     * @throws \RuntimeException
     * @throws BadRequestHttpException
     *
     * @return RedirectResponse
     */
    public function filterAction(Request $request, $path, $filter)
    {
        $url = $request->get('url');
        $resolver = $request->get('resolver');

        try {
            if (!$this->cacheManager->isStored($path, $filter, $resolver)) {
                if (!@file_get_contents($path)) {
                    try {
                        if ($content = $this->fetchUrl($url)) {
                            if(!file_exists(dirname($path))) {
                                mkdir(dirname($path), 0755, true);
                            }
                            $fp = fopen($path, "wb");
                            fwrite($fp, $content);
                            fclose($fp);
                        } else {
                            $path = self::IMAGES_PATH . self::DEFAULT_IMAGE_FILE;
                        }

                    } catch (\Exception $e) {
                        $path = self::IMAGES_PATH . self::DEFAULT_IMAGE_FILE;
                    }
                }

                $binary = $this->dataManager->find($filter, $path);
                $this->cacheManager->store(
                    $this->filterManager->applyFilter($binary, $filter),
                    $path,
                    $filter,
                    $resolver
                );
            }

            return new RedirectResponse($this->cacheManager->resolve($path, $filter, $resolver), $this->redirectResponseCode);
        } catch (NonExistingFilterException $e) {
            $message = sprintf('Could not locate filter "%s" for path "%s". Message was "%s"', $filter, $path, $e->getMessage());

            if (null !== $this->logger) {
                $this->logger->debug($message);
            }

            throw new NotFoundHttpException($message, $e);
        } catch (RuntimeException $e) {
            throw new \RuntimeException(sprintf('Unable to create image for path "%s" and filter "%s". Message was "%s"', $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    protected function fetchUrl($uri) {
        $handle = curl_init();

        curl_setopt($handle, CURLOPT_URL, $uri);
        curl_setopt($handle, CURLOPT_POST, false);
        curl_setopt($handle, CURLOPT_BINARYTRANSFER, false);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);

        $response = curl_exec($handle);
        $hLength  = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $body     = substr($response, $hLength);

        if ($httpCode >= 400) {
            throw new \Exception($httpCode);
        }

        return $body;
    }
}
