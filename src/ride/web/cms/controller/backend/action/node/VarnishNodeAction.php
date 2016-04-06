<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\i18n\translator\Translator;
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
     * Available cache times
     * @var array
     */
    private $times = array(
        60, // 1 minute
        300, // 5 minutes
        900, // 15 minutes
        1800, // 30 minutes
        3600, // 1 hour
        10800, // 3 hours
        21600, // 6 hours
        43200, // 12 hours
        86400, // 1 day
        604800, // 1 week
        2628000, // 1 month
        7884000, // 3 months
        15768000, // 6 months
        31536000, // 1 year
    );

    /**
     * Perform the structure node action
     */
    public function indexAction(Cms $cms, CmsVarnishService $varnishService, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.node.varnish', array(
                'locale' => $locale,
                'site' => $site->getId(),
                'revision' => $site->getRevision(),
                'node' => $node->getId(),
            ));
        }

        $translator = $this->getTranslator();

        $cache = $node->get('cache.target', 'inherit', false);
        $data = array(
            'cacheTarget' => $cache,
            'sharedMaxAge' => in_array($cache, array('intermediate', 'all')) ? $node->getHeader($locale, 's-maxage') : null,
            'maxAge' => $cache == 'all' ? $node->getHeader($locale, 'max-age') : ($cache == 'intermediate' ? 0 : null),
        );
        $formHeaders = null;
        if ($this->isPermissionGranted('cms.node.varnish.manage')) {
            $formHeaders = $this->createFormBuilder($data);
            $formHeaders->setAction('headers');
            $formHeaders->addRow('cacheTarget', 'option', array(
                'label' => $translator->translate('label.cache.target'),
                'options' => $this->getCacheOptions($node, $translator, $locale),
                'attributes' => array(
                    'data-toggle-dependant' => 'option-cachetarget',
                ),
                'validators' => array(
                    'required' => array(),
                ),
            ));
            $formHeaders->addRow('maxAge', 'select', array(
                'label' => $translator->translate('label.header.maxage'),
                'description' => $translator->translate('label.header.maxage.description'),
                'options' => $this->getTimeOptions($translator, 0, 3600),
                'attributes' => array(
                    'class' => 'option-cachetarget option-cachetarget-all',
                ),
            ));
            $formHeaders->addRow('sharedMaxAge', 'select', array(
                'label' => $translator->translate('label.header.smaxage'),
                'description' => $translator->translate('label.header.smaxage.description'),
                'options' => $this->getTimeOptions($translator, 0, 31536000),
                'attributes' => array(
                    'class' => 'option-cachetarget option-cachetarget-intermediate option-cachetarget-all',
                ),
            ));

            $formHeaders = $formHeaders->build();
            if ($formHeaders->isSubmitted()) {
                try {
                    $formHeaders->validate();

                    $data = $formHeaders->getData();

                    $node->set('cache.target', $data['cacheTarget']);
                    $node->setHeader($locale, 'max-age', $data['cacheTarget'] == 'all' ? $data['maxAge'] : ($data['cacheTarget'] == 'intermediate' ? 0 : ($data['cacheTarget'] == 'inherit' ? null : '')));
                    $node->setHeader($locale, 's-maxage', in_array($data['cacheTarget'], ['intermediate', 'all']) ? $data['sharedMaxAge'] : ($data['cacheTarget'] == 'inherit' ? null : ''));
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

            $formHeaders = $formHeaders->getView();
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
            'formHeaders' => $formHeaders,
            'formClear' => $formClear->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Gets the cache value
     * @param string $security Form value
     * @return null|string
     */
    private function getCacheValue($cacheTarget) {
        if ($cacheTarget == 'inherit') {
            return null;
        } else {
            return $cacheTarget;
        }
    }

    /**
     * Gets the cache options
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @return array Array with the cache code as key and the translation as
     * value
     */
    protected function getCacheOptions(Node $node, Translator $translator, $locale) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $options['inherit'] = $translator->translate('label.inherited') . $this->getInheritedCacheOption($parentNode, $translator, $locale);
        }

        $options['none'] = $translator->translate('label.cache.target.none');
        $options['intermediate'] = $translator->translate('label.cache.target.intermediate');
        $options['all'] = $translator->translate('label.cache.target.all');

        return $options;
    }

    /**
     * Gets the inherited cache options
     * @param \ride\library\cms\node\Node $parentNode
     * @param \ride\library\i18n\translator\Translator $translator
     * @param $locale
     *
     * @return string
     */
    protected function getInheritedCacheOption(Node $parentNode, Translator $translator, $locale) {
        $value = $parentNode->get('cache.target', null, true, true);
        $maxAge = $parentNode->getHeader($locale, 'max-age');
        $sharedMaxAge = $parentNode->getHeader($locale, 's-maxage');

        if(empty($value)) {
            return "";
        }

        $suffix = ' (';
        $suffix .= $translator->translate('label.cache.target.' . $value);
        $suffix .= in_array($value, ['inherit', 'all']) ? ', ' . $translator->translate('label.header.maxage') . ': ' . $translator->translate('label.cache.time.' . $maxAge) : null;
        $suffix .= in_array($value, ['inherit', 'intermediate', 'all']) ? ', ' . $translator->translate('label.header.smaxage') . ': ' . $translator->translate('label.cache.time.' . $sharedMaxAge) : null;
        $suffix .= ')';

        return $suffix;
    }

    /**
     * Gets the time options between a given range
     * @param \ride\library\i18n\translator\Translator $translator
     * @param integer $min
     * @param integer $max
     * @return array
     */
    protected function getTimeOptions(Translator $translator, $min, $max) {
        $options = array();

        foreach ($this->times as $time) {
            if ($time >= $min && $time <= $max) {
                $options[$time] = $translator->translate('label.cache.time.' . $time);
            }
        }

        if ($min < 86400 && 86400 < $max) {
            $options['end-of-day'] = $translator->translate('label.cache.time.end.day');
        }

        return $options;
    }

}
