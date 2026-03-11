<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\EventListener;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Ibexa\Contracts\AdminUi\Menu\MenuItemFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CleanBlogAdminMenuListener implements EventSubscriberInterface
{
    public const string ITEM_ADMIN__CONTACT_SUBMISSIONS = 'main__admin__contact_submissions';

    private MenuItemFactoryInterface $menuItemFactory;

    public function __construct(MenuItemFactoryInterface $menuItemFactory)
    {
        $this->menuItemFactory = $menuItemFactory;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMenuEvent::MAIN_MENU => 'onMenuConfigure',
        ];
    }

    public function onMenuConfigure(ConfigureMenuEvent $event): void
    {
        $menu = $event->getMenu();

        $menu->addChild(
            $this->menuItemFactory->createItem(
                self::ITEM_ADMIN__CONTACT_SUBMISSIONS,
                [
                    'route' => 'clean_blog.admin.contact_submissions',
                    'label' => 'Contact Submissions',
                    'extras' => [
                        'icon' => 'mail',
                        'orderNumber' => 100,
                    ],
                ],
            )
        );
    }
}
