<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace App\Twig;

use App\Twig\Templating\HeadLink;
use App\Twig\Templating\HeadMeta;
use App\Twig\Templating\HeadScript;
use App\Twig\Templating\HeadStyle;
use App\Twig\Templating\HeadTitle;
//use App\Twig\Templating\InlineScript;
//use App\Twig\Templating\Placeholder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class HeaderExtension extends AbstractExtension
{
    /**
     * @var HeadLink
     */
    public $headLink;

    /**
     * @var HeadMeta
     */
    public $headMeta;

    /**
     * @var HeadScript
     */
    public $headScript;

    /**
     * @var HeadStyle
     */
    public $headStyle;

    /**
     * @var HeadTitle
     */
    public $headTitle;

    /**
     * @var InlineScript
     */
    //private $inlineScript;

    /**
     * @var Placeholder
     */
    //private $placeholder;
    
    /**
     * @param HeadLink $headLink
     * @param HeadMeta $headMeta
     * @param HeadScript $headScript
     * @param HeadStyle $headStyle
     * @param HeadTitle $headTitle
     *
     */
    public function __construct(HeadLink $headLink, HeadMeta $headMeta, HeadScript $headScript, HeadStyle $headStyle, HeadTitle $headTitle)
    {
        $this->headLink = $headLink;
        $this->headMeta = $headMeta;
        $this->headScript = $headScript;
        $this->headStyle = $headStyle;
        $this->headTitle = $headTitle;
        //$this->inlineScript = $inlineScript;
        //$this->placeholder = $placeholder;
    }
    
    public function setWebroot($value){
        if(!defined('WEB_ROOT')){
            define('WEB_ROOT', $value);
        }
    }

    public function getFunctions(): array
    {
        $options = [
            'is_safe' => ['html'],
        ];
        
        // as runtime extension classes are invokable, we can pass them directly as callable
        return [
            new TwigFunction('head_link', $this->headLink, $options),
            new TwigFunction('head_meta', $this->headMeta, $options),
            new TwigFunction('head_script', $this->headScript, $options),
            new TwigFunction('head_style', $this->headStyle, $options),
            new TwigFunction('head_title', $this->headTitle, $options),
        ];
    }
}
