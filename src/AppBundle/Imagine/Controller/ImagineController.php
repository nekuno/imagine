<?php

namespace AppBundle\Imagine\Controller;

use Liip\ImagineBundle\Controller\ImagineController as DefaultImagineController;
use Imagine\Exception\RuntimeException;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
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
        $path = str_replace('!', '%', $path);
        $url = $path;
        try {
            file_get_contents(urldecode($path));
            $fileName = preg_replace("/[^a-zA-Z0-9\\-\\_\\/\\.]+/", "", $path);
            $path = self::IMAGES_PATH . $fileName;
        } catch (\Exception $e) {
            $path = self::IMAGES_PATH . self::DEFAULT_IMAGE_FILE;
        }
        $resolver = $request->get('resolver');

        try {
            if (!$this->cacheManager->isStored($path, $filter, $resolver)) {
                try {
                    $binary = $this->dataManager->find($filter, $url);
                } catch (NotLoadableException $e) {
                    if ($defaultImageUrl = $this->dataManager->getDefaultImageUrl($filter)) {
                        return new RedirectResponse($defaultImageUrl);
                    }

                    throw new NotFoundHttpException('Source image could not be found', $e);
                }

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
}
