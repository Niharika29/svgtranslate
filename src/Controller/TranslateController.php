<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Model\Svg\SvgFile;
use App\Model\Title;
use App\OOUI\TranslationsFieldset;
use App\Service\FileCache;
use App\Service\Uploader;
use Krinkle\Intuition\Intuition;
use OOUI\ButtonInputWidget;
use OOUI\DropdownInputWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TranslateController extends AbstractController
{

    /**
     * The translate page.
     *
     * The 'prefix' part of this route is actually a fixed string 'File', but in order to make it
     * case-insensitive we have to make it a route variable with a regex requirement. Then we can
     * also check it and redirect to the canonical form when required.
     * @Route( "/{prefix}:{filename}",
     *     name="translate",
     *     methods={"GET"},
     *     requirements={"prefix"="(?i:File)", "filename"="(.+)"}
     *     )
     */
    public function translate(
        Request $request,
        Intuition $intuition,
        Session $session,
        FileCache $cache,
        string $filename,
        string $prefix = 'File'
    ): Response {
        $normalizedFilename = Title::normalize($filename);

        // Redirect to normalized URL if required.
        if ('File' !== $prefix || $filename !== $normalizedFilename) {
            return $this->redirectToRoute('translate', ['filename' => $normalizedFilename]);
        }

        // Fetch the SVG from Commons.
        $path = $cache->getPath($filename);
        $svgFile = new SvgFile($path);

        // Upload and download buttons.
        $downloadButton = new ButtonInputWidget([
            'label' => $intuition->msg('download-button-label'),
            'flags' => [ 'progressive' ],
            'type' => 'submit',
            'framed' => false,
        ]);
        $uploadButton = new ButtonInputWidget([
            'label' => $intuition->msg('upload-button-label'),
            'flags' => [ 'progressive' ],
            'type' => 'submit',
            'icon' => 'logoWikimediaCommons',
            'name' => 'upload',
            'id' => 'upload-button-widget',
            'infusable' => true,
        ]);
        if (!$session->get('logged_in_user')) {
            // Only logged in users can upload.
            $uploadButton->setDisabled(true);
        }

        // Source and target language selectors.
        $availableLangs = [
            ['data' => 'fallback', 'label' => $intuition->msg('default-language')],
        ];
        foreach ($svgFile->getSavedLanguages() as $lang) {
            $langName = $intuition->getLangName($lang);
            if ($langName) {
                $availableLangs[] = [
                    'data' => $lang,
                    'label' => $langName,
                ];
            }
        }
        $sourceLang = new DropdownInputWidget([
            'label' => $intuition->msg('source-lang-label'),
            'options' => $availableLangs,
            // @TODO Get this value from the session.
            'value' => 'fallback',
            'classes' => ['source-lang-widget'],
            'infusable' => true,
        ]);
        $targetLangDefault = $intuition->getLang();
        $cookie = $request->cookies->get('svgtranslate');
        if ($cookie) {
            $cookieValue = json_decode($cookie);
            if (isset($cookieValue->interfaceLang)) {
                $targetLangDefault = $cookieValue->interfaceLang;
            }
        }
        $targetLang = new ButtonInputWidget([
            'label' => $intuition->getLangName($targetLangDefault),
            'value' => $targetLangDefault,
            'classes' => ['target-lang-widget'],
            'indicator' => 'down',
            'infusable' => true,
            'name' => 'target-lang',
        ]);
        $languageSelectorsLayout = new HorizontalLayout([
            'items' => [
                $sourceLang,
                new LabelWidget([
                    'label' => $intuition->msg('source-to-target'),
                    'classes' => ['source-to-target-label'],
                ]),
                $targetLang,
            ],
            'classes' => ['language-selectors'],
        ]);

        // Form fields for translation messages are in a fieldset which contains fieldsets for each group of messages.
        $translations = $svgFile->getInFileTranslations();
        $translationsFieldset = new TranslationsFieldset([
            'translations' => $translations,
            'source_lang_code' => $sourceLang->getValue(),
            'target_lang_code' => $targetLang->getValue(),
        ]);

        return $this->render('translate.html.twig', [
            'page_class' => 'translate',
            'title' => Title::text($filename),
            'filename' => $normalizedFilename,
            'translation_fieldset' => $translationsFieldset,
            'download_button' => $downloadButton,
            'upload_button' => $uploadButton,
            'language_selectors' => $languageSelectorsLayout,
            'translations' => $translations,
            'target_lang' => $targetLangDefault,
        ]);
    }

    /**
     * @Route( "/File:{filename}", name="updownload", methods={"POST"})
     */
    public function updownload(
        string $filename,
        Request $request,
        FileCache $cache,
        Uploader $uploader
    ): Response {
        $requestParams = $request->request->all();

        // Are we uploading or downloading?
        $isUpload = false;
        if (isset($requestParams['upload'])) {
            $isUpload = true;
            unset($requestParams['upload']);
        }

        // Target language.
        $targetLang = $requestParams['target-lang'];
        unset($requestParams['target-lang']);

        // Add the translations to the file and save it to the filesystem.
        $file = new SvgFile($cache->getPath(Title::normalize($filename)));
        $file->setTranslations($targetLang, $requestParams);
        $tmpFilename = $cache->fullPath($filename.uniqid().'.svg');
        file_put_contents($tmpFilename, $file->saveToString());

        // Download or upload.
        if (!$isUpload) {
            // Prompt for download.
            return new BinaryFileResponse(
                $tmpFilename,
                200,
                [],
                false,
                ResponseHeaderBag::DISPOSITION_ATTACHMENT
            );
        } else {
            // Send to Commons.
            $url = $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $comment = "File uploaded using svgtranslate tool ($url). Added translation for $targetLang.";
            $url = $uploader->upload($tmpFilename, $filename, $comment);
            $this->addFlash('upload-complete', $url);
            return $this->redirectToRoute('translate', ['filename' => $filename]);
        }
    }
}
