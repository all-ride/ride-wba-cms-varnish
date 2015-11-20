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
            'sharedMaxAge' => $node->getHeader($locale, 's-maxage'),
            'maxAge' => $node->getHeader($locale, 'max-age'),
            'noCache' => $node->getLocalized($locale, 'cache.disabled'),
            'maxAge-show' => $node->getLocalized($locale, 'maxage.show'),
            'sharedMaxAge-show' => $node->getLocalized($locale, 'sharedmaxage.show')
        );

        $formHeaders = $this->createFormBuilder($data);
        $formHeaders->setAction('headers');

        $formHeaders->addRow('maxAge-show', 'option', array(
            'label' => $translator->translate('label.age.max.show'),
            'description' => $translator->translate('label.age.max.show.description'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-maxage',
            ),
        ));
        $formHeaders->addRow('maxAge', 'number', array(
            'label' => $translator->translate('label.age.max'),
            'description' => $translator->translate('label.age.max.description'),
            'validators' => array(
                'minmax' => array(
                    'minimum' => 0,
                ),
            ),
            'attributes' => array(
                'class' => 'option-maxage option-maxage-1',
            ),
        ));

        $formHeaders->addRow('sharedMaxAge-show', 'option', array(
            'label' => $translator->translate('label.age.sharedmax.show'),
            'description' => $translator->translate('label.age.sharedmax.show.description'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-sharedmax',
            ),
        ));

        $formHeaders->addRow('sharedMaxAge', 'number', array(
            'label' => $translator->translate('label.sharedage.max'),
            'description' => $translator->translate('label.age.sharedmax.description'),
            'validators' => array(
                'minmax' => array(
                    'minimum' => 0,
                ),
            ),
            'attributes' => array(
                'class' => 'option-sharedmax option-sharedmax-1',
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

                $node->setLocalized($locale, 'cache.disabled', $data['noCache']);
                $node->setLocalized($locale, 'maxage.show', $data['maxAge-show'] ? $data['maxAge-show'] : 0);
                $node->setLocalized($locale, 'sharedmaxage.show', $data['sharedMaxAge-show'] ? $data['sharedMaxAge-show'] : 0);
                $node->setHeader($locale, 's-maxage', $data['sharedMaxAge-show'] ? $data['sharedMaxAge'] : null);
                $node->setHeader($locale, 'max-age', $data['maxAge-show'] ? $data['maxAge'] : null);
                $node->setHeader($locale, 'Expires', 'Wed, 06 Jul 1983 5:00:00 GMT');

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
