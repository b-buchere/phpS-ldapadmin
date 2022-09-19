<?php
namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;
use Sg\DatatablesBundle\Datatable\AbstractDatatable;
use Sg\DatatablesBundle\Datatable\Ajax;
use Sg\DatatablesBundle\Datatable\Callbacks;
use Sg\DatatablesBundle\Datatable\Events;
use Sg\DatatablesBundle\Datatable\Extensions;
use Sg\DatatablesBundle\Datatable\Features;
use Sg\DatatablesBundle\Datatable\Language;
use Sg\DatatablesBundle\Datatable\Options;
use Sg\DatatablesBundle\Datatable\Column\ColumnBuilder;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use LogicException;

/**
 *
 * @author cheat
 *        
 */
class BaseDatatable extends AbstractDatatable
{

    
    /**
     * @throws LogicException
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $securityToken,
        TranslatorInterface $translator,
        RouterInterface $router,
        EntityManagerInterface $em,
        Environment $twig
        ) {
            $this->validateName();
            
            if (isset(self::$uniqueCounter[$this->getName()])) {
                $this->uniqueId = ++self::$uniqueCounter[$this->getName()];
            } else {
                $this->uniqueId = self::$uniqueCounter[$this->getName()] = 1;
            }
            
            $this->authorizationChecker = $authorizationChecker;
            $this->securityToken = $securityToken;
            
            if ( ! ($translator instanceof TranslatorInterface)) {
                throw new \InvalidArgumentException(sprintf('The $translator argument of %s must be an instance of %s a %s was given.', static::class, TranslatorInterface::class, \get_class($translator)));
            }
            $this->translator = $translator;
            $this->router = $router;
            $this->em = $em;
            $this->twig = $twig;
            
            $metadata = $em->getClassMetadata($this->getEntity());
            $this->columnBuilder = new ColumnBuilder($metadata, $twig, $router, $this->getName(), $em);

            $this->ajax = new Ajax();
            $this->options = new Options();
            $this->features = new Features();
            $this->callbacks = new Callbacks();
            $this->events = new Events();
            $this->extensions = new Extensions();
            $this->language = new Language();
            
            $this->accessor = PropertyAccess::createPropertyAccessor();
    }
    /**
     * (non-PHPdoc)
     *
     * @see \Sg\DatatablesBundle\Datatable\DatatableInterface::getName()
     */
    public function getName()
    {}

    /**
     * (non-PHPdoc)
     *
     * @see \Sg\DatatablesBundle\Datatable\DatatableInterface::buildDatatable()
     */
    public function buildDatatable(array $options = [])
    {}

    /**
     * (non-PHPdoc)
     *
     * @see \Sg\DatatablesBundle\Datatable\DatatableInterface::getEntity()
     */
    public function getEntity()
    {}
    
    //-------------------------------------------------
    // Private
    //-------------------------------------------------
    
    /**
     * Checks the name only contains letters, numbers, underscores or dashes.
     *
     * @throws LogicException
     */
    private function validateName()
    {
        $name = $this->getName();
        if (1 !== preg_match(self::NAME_REGEX, $name)) {
            throw new LogicException(sprintf('AbstractDatatable::validateName(): "%s" is invalid Datatable Name. Name can only contain letters, numbers, underscore and dashes.', $name));
        }
    }
}

