<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\WebBundle\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\Matcher\Voter\UriVoter;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Sylius\Bundle\CartBundle\Provider\CartProviderInterface;
use Sylius\Bundle\MoneyBundle\Twig\SyliusMoneyExtension;
use Sylius\Bundle\ResourceBundle\Model\RepositoryInterface;
use Sylius\Bundle\TaxonomiesBundle\Model\TaxonInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;

use Jiwen\BannerBundle\Entity\Banner;
use Jiwen\GeneralBundle\JiwenGeneralBundle;

/**
 * Frontend menu builder.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
class FrontendMenuBuilder extends MenuBuilder
{
    /**
     * Taxonomy repository.
     *
     * @var RepositoryInterface
     */
    protected $taxonomyRepository;

    /**
     * Cart provider.
     *
     * @var CartProviderInterface
     */
    protected $cartProvider;

    /**
     * Money extension.
     *
     * @var SyliusMoneyExtension
     */
    protected $moneyExtension;

    /**
     * Constructor.
     *
     * @param FactoryInterface         $factory
     * @param SecurityContextInterface $securityContext
     * @param TranslatorInterface      $translator
     * @param RepositoryInterface      $taxonomyRepository
     * @param CartProviderInterface    $cartProvider
     */
    public function __construct(
        FactoryInterface         $factory,
        SecurityContextInterface $securityContext,
        TranslatorInterface      $translator,
        RepositoryInterface      $taxonomyRepository,
        CartProviderInterface    $cartProvider,
        SyliusMoneyExtension     $moneyExtension
    )
    {
        parent::__construct($factory, $securityContext, $translator);

        $this->taxonomyRepository = $taxonomyRepository;
        $this->cartProvider = $cartProvider;
        $this->moneyExtension = $moneyExtension;
    }

    /**
     * Builds frontend main menu.
     *
     * @param Request $request
     *
     * @return ItemInterface
     */
    public function createMainMenu(Request $request)
    {
        $menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav nav-pills pull-right'
            )
        ));

        $menu->setCurrent($request->getRequestUri());

        $cart = $this->cartProvider->getCart();

        $menu->addChild('cart', array(
            'route' => 'sylius_cart_summary',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.cart', array(
                    '%items%' => $cart->getTotalItems(),
                    '%total%' => $this->moneyExtension->formatMoney($cart->getTotal())
            ))),
            'labelAttributes' => array('icon' => 'icon-shopping-cart icon-large')
        ))->setLabel($this->translate('sylius.frontend.menu.main.cart', array(
                    '%items%' => $cart->getTotalItems(),
                    '%total%' => $this->moneyExtension->formatMoney($cart->getTotal())
                )));

        if ($this->securityContext->isGranted('ROLE_USER')) {
            $menu->addChild('account', array(
                'route' => 'sylius_account_homepage',
                'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.account')),
                'labelAttributes' => array('icon' => 'icon-user icon-large', 'iconOnly' => false)
            ))->setLabel($this->translate('sylius.frontend.menu.main.account'));
            $menu->addChild('logout', array(
                'route' => 'fos_user_security_logout',
                'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.logout')),
                'labelAttributes' => array('icon' => 'icon-off icon-large', 'iconOnly' => false)
            ))->setLabel($this->translate('sylius.frontend.menu.main.logout'));
        } else {
            $menu->addChild('login', array(
                'route' => 'fos_user_security_login',
                'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.login')),
                'labelAttributes' => array('icon' => 'icon-lock icon-large', 'iconOnly' => false)
            ))->setLabel($this->translate('sylius.frontend.menu.main.login'));
            $menu->addChild('register', array(
                'route' => 'fos_user_registration_register',
                'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.register')),
                'labelAttributes' => array('icon' => 'icon-user icon-large', 'iconOnly' => false)
            ))->setLabel($this->translate('sylius.frontend.menu.main.register'));
        }

        if ($this->securityContext->isGranted('ROLE_SYLIUS_ADMIN')) {
            $menu->addChild('administration', array(
                'route' => 'sylius_backend_dashboard',
                'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.main.administration')),
                'labelAttributes' => array('icon' => 'icon-briefcase icon-large', 'iconOnly' => false)
            ))->setLabel($this->translate('sylius.frontend.menu.main.administration'));
        }

        return $menu;
    }

    /**
     * Builds frontend taxonomies menu.
     *
     * @param Request $request
     *
     * @return ItemInterface
     */
    public function createTaxonomiesMenu(Request $request)
    {
        $menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav nav-list'
            )
        ));

        $menu->setCurrent($request->getRequestUri());

        $taxonomyCategory = $this->taxonomyRepository->findOneByName('Category')->getRoot();

		//将三级分类合并之后变为二级分类
		foreach($taxonomyCategory->getChildren() as $key => $child) { // 一级分类 教会用品，图书音像等
			foreach($child->getChildren() as $skey => $schild) // schild为二级分类,图书，音像
			{
				if($schild->getChildren()->isEmpty() == false) {// 如果是有三级分类，需要把所有的三级分类合并为二级分类
					// 例如，当前一级分类为图书音像，$key为2，
					// 需要遍历该一级分类下的二级分类下的三级分类，将三级分类合并之后
					// 替代二级分类
					
					$this->updateTaxon($taxonomyCategory, $key);
					break;
				}
			}
		}
        $this->createTaxonomiesMenuNode($menu, $taxonomyCategory);

        return $menu;
    }

	/**
	 * 合并三级分类到二级分类
	 * @param type $taxonomyCategory 一级分类
	 * @param type $key 需要合并的一级分类的key
	 */
	public function updateTaxon($taxonomyCategory, $key)
	{
		$children = $taxonomyCategory->getChildren();
		foreach($children[$key]->getChildren() as $child) {// child下面的分类需要合并为一个分类
			$subchild = $child->getChildren();
			foreach($subchild as $sub_key => $sub_child) {
				$children[$key]->addChild($sub_child);
			}
			$children[$key]->removeChild($child);
		}
	}

    /**
     * Builds frontend taxonomies menu.
     *
     * @param Request $request
     *
     * @return ItemInterface
     */
    public function createTaxonomiesMenuArray(Request $request)
    {
        $menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav nav-list'
            )
        ));

        $menu->setCurrent($request->getRequestUri());

        $taxonomy = $this->taxonomyRepository->findOneByName('Category');

        $this->createTaxonomiesMenuNode($menu, $taxonomy->getRoot());

        return $menu->toArray();
    }

    private function createTaxonomiesMenuNode(ItemInterface $menu, TaxonInterface $taxon, $top = true)
    {
        $em = JiwenGeneralBundle::getContainer()->get('doctrine')->getEntityManager('default');
        foreach ($taxon->getChildren() as $child) {
			$banner = $em->getRepository('JiwenBannerBundle:Banner')->findOneMenuBanner($child);
            $childMenu = $menu->addChild($child->getName(), array(
                'route'           => 'sylius_product_index_by_taxon',
                'routeParameters' => array('permalink' => $child->getPermalink()),
                'labelAttributes' => array('icon' => 'icon-angle-right',
					'banner' => $banner,
					),
            ));
            if ($child->getPath()) {
                $childMenu->setLabelAttribute('data-image', $child->getPath());
            }

            $this->createTaxonomiesMenuNode($childMenu, $child, false);
        }
    }

    /**
     * Builds frontend social menu.
     *
     * @param Request $request
     *
     * @return ItemInterface
     */
    public function createSocialMenu(Request $request)
    {
        $menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav nav-pills pull-right'
            )
        ));

        $menu->addChild('github', array(
            'uri' => 'https://github.com/Sylius',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.social.github')),
            'labelAttributes' => array('icon' => 'icon-github-sign icon-large', 'iconOnly' => true)
        ));
        $menu->addChild('twitter', array(
            'uri' => 'https://twitter.com/Sylius',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.social.twitter')),
            'labelAttributes' => array('icon' => 'icon-twitter-sign icon-large', 'iconOnly' => true)
        ));
        $menu->addChild('facebook', array(
            'uri' => 'http://facebook.com/SyliusEcommerce',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.social.facebook')),
            'labelAttributes' => array('icon' => 'icon-facebook-sign icon-large', 'iconOnly' => true)
        ));
        $menu->addChild('linkedin', array(
            'uri' => 'http://www.linkedin.com/groups/Sylius-Community-4903257',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.social.linkedin')),
            'labelAttributes' => array('icon' => 'icon-linkedin-sign icon-large', 'iconOnly' => true)
        ));

        return $menu;
    }

    /**
     * Creates user account menu
     *
     * @param Request $request
     *
     * @return ItemInterface
     */
    public function createAccountMenu(Request $request)
    {
        $menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav'
            )
        ));

        $menu->setCurrent($request->getRequestUri());

        $childOptions = array(
            'childrenAttributes' => array('class' => 'nav nav-list'),
            'labelAttributes'    => array('class' => 'nav-header')
        );

        $child = $menu->addChild($this->translate('sylius.account.title'), $childOptions);

        $child->addChild('account', array(
            'route' => 'sylius_account_homepage',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.homepage')),
            'labelAttributes' => array('icon' => 'icon-home', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.homepage'));

        $child->addChild('order_history', array(
            'route' => 'sylius_account_order',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.order_history')),
            'labelAttributes' => array('icon' => 'icon-list', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.order_history'));

        $child->addChild('bookmark', array(
            'route' => 'jiwen_bookmark_index',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.bookmark')),
            'labelAttributes' => array('icon' => 'icon-list', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.bookmark'));

        $child->addChild('profile', array(
            'route' => 'fos_user_profile_edit',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.profile')),
            'labelAttributes' => array('icon' => 'icon-info-sign', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.profile'));

        $child->addChild('password', array(
            'route' => 'fos_user_change_password',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.password')),
            'labelAttributes' => array('icon' => 'icon-lock', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.password'));

        $child->addChild('shop', array(
            'route' => 'sylius_homepage',
            'linkAttributes' => array('title' => $this->translate('sylius.frontend.menu.account.shop')),
            'labelAttributes' => array('icon' => 'icon-shopping-cart', 'iconOnly' => false)
        ))->setLabel($this->translate('sylius.frontend.menu.account.shop'));

        return $menu;
    }
}
