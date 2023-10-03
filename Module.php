<?php

namespace ScriptoIiif;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Property;
use Omeka\Entity\Vocabulary;
use Omeka\Module\AbstractModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Scripto\Entity\ScriptoMedia;
use ScriptoIiif\Form\ConfigForm;

class Module extends AbstractModule
{
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('*', 'iiif_presentation.3.media.canvas', [$this, 'onIiifPresentation3MediaCanvas']);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $forms = $services->get('FormElementManager');
        $form = $forms->get(ConfigForm::class);

        $form->setData([
            'scriptoiiif_motivation' => $settings->get('scriptoiiif_motivation'),
        ]);

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $forms = $services->get('FormElementManager');
        $form = $forms->get(ConfigForm::class);
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addFormErrors($form);
            return false;
        }

        $formData = $form->getData();
        $settings->set('scriptoiiif_motivation', $formData['scriptoiiif_motivation']);

        return true;
    }

    public function onIiifPresentation3MediaCanvas(Event $event)
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $em = $services->get('Omeka\EntityManager');
        $adapters = $services->get('Omeka\ApiAdapterManager');
        $settings = $services->get('Omeka\Settings');

        $controller = $event->getTarget();
        $canvas = $event->getParam('canvas');
        $mediaId = $event->getParam('media_id');

        $scriptoMedias = $em->getRepository(ScriptoMedia::class)->findBy(['media' => $mediaId]);
        if (empty($scriptoMedias)) {
            return;
        }

        $dctermsVocabulary = $em->getRepository(Vocabulary::class)->findOneBy(['prefix' => 'dcterms']);
        $dctermsContributorProperty = $em->getRepository(Property::class)->findOneBy(['vocabulary' => $dctermsVocabulary, 'localName' => 'contributor']);

        foreach ($scriptoMedias as $scriptoMedia) {
            $scriptoItem = $scriptoMedia->getScriptoItem();
            $scriptoProject = $scriptoItem->getScriptoProject();
            $property = $scriptoProject->getProperty();

            $media = $scriptoMedia->getMedia();
            $criteria = Criteria::create()->where(Criteria::expr()->eq('property', $property));
            $values = $media->getValues()->matching($criteria);
            foreach ($values as $value) {
                $annotationPageId = $controller->url()->fromRoute('iiif-presentation-3/item/canvas', ['item-id' => $media->getItem()->getId(), 'media-id' => $media->getId()], ['force_canonical' => true]) . '/scripto-' . $value->getId();
                $annotation = [
                    'id' => $annotationPageId . '/annotation',
                    'type' => 'Annotation',
                    'label' => [
                        'en' => [ $property->getLabel() ],
                    ],
                    'motivation' => $settings->get('scriptoiiif_motivation') ?: 'supplementing',
                    'body' => [
                        'type' => 'TextualBody',
                        'format' => 'text/plain',
                        'value' => $value->getValue(),
                    ],
                    'target' => $canvas['id'],
                ];
                $valueAnnotation = $value->getValueAnnotation();
                if ($valueAnnotation) {
                    $valueAnnotationRepresentation = $adapters->get('value_annotations')->getRepresentation($valueAnnotation);
                    $annotation['metadata'] = $controller->iiifPresentation3()->getMetadata($valueAnnotationRepresentation);
                }
                $annotationPage = [
                    'id' => $annotationPageId,
                    'type' => 'AnnotationPage',
                    'items' => [ $annotation ],
                ];
                $canvas['annotations'][] = $annotationPage;
            }
        }

        $event->setParam('canvas', $canvas);
    }

    public function getConfig()
    {
        return require __DIR__ . '/config/module.config.php';
    }
}
