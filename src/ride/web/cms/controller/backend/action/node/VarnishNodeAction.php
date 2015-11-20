<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

use ride\service\CmsVarnishService;
use ride\web\cms\Cms;

/**
 * Controller of the varnish node action
 */
class VarnishNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'varnish';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.varnish';

    /**
     * Perform the structure node action
     */
    public function indexAction(Cms $cms, CmsVarnishService $varnishService, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $cms->setLastAction(self::NAME);

        $url = $site->getBaseUrl($locale);
        if (!$url) {
            $url = $this->request->getBaseUrl();
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.node.varnish', array(
                'locale' => $locale,
                'site' => $site->getId(),
                'revision' => $site->getRevision(),
                'node' => $node->getId(),
            ));
        }

        $data = array(
            'maxAge' => $node->getHeader($locale, 's-maxage'),
            'noCache' => $node->get('cache.disabled')
        );

        $formHeaders = $this->createFormBuilder($data);
        $formHeaders->setAction('headers');
        $formHeaders->addRow('maxAge', 'number', array(
            'label' => $translator->translate('label.age.max'),
            'description' => $translator->translate('label.age.max.description'),
            'validators' => array(
                'minmax' => array(
                    'minimum' => 0,
                ),
            ),
        ));

        $formHeaders->addRow('noCache', 'option', array(
            'label' => $translator->translate('label.no.cache'),
            'description' => $translator->translate('label.no.cache.description')
            )
        );

        $formHeaders = $formHeaders->build();
        if ($formHeaders->isSubmitted()) {
            try {
                $formHeaders->validate();

                $data = $formHeaders->getData();

                $node->set('cache.disabled', $data['noCache']);
                $node->setHeader($locale, 's-maxage', $data['maxAge']);
                $node->setHeader($locale, 'Expires', 'Wed, 06 Jul 1983 5:00:00 GMT'); // Mr. Kaya's b-day!

                $cms->saveNode($node, "Set cache properties for " . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale)
                ));

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $formHeaders);
            }
        }

        $formClear = $this->createFormBuilder();
        $formClear->setAction('clear');
        $formClear->addRow('recursive', 'option', array(
            'label' => '',
            'description' => $translator->translate('label.confirm.varnish.clear.recursive'),
        ));

        $formClear = $formClear->build();
        if ($formClear->isSubmitted()) {
            $baseUrl = $site->getBaseUrl($locale);
            if (!$baseUrl) {
                $baseUrl = $this->request->getBaseUrl();
            }

            $data = $formClear->getData();

            $varnishService->banNode($node, $baseUrl, $locale, $data['recursive']);

            $this->addSuccess('success.node.varnish.cleared', array(
                'node' => $node->getName($locale),
            ));

            $this->response->setRedirect($referer);

            return;
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.varnish', array(
            'site' => $site,
            'node' => $node,
            'formHeaders' => $formHeaders->getView(),
            'formClear' => $formClear->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
