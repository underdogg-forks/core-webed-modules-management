<?php namespace WebEd\Base\ModulesManagement\Http\Controllers;

use WebEd\Base\Core\Http\Controllers\BaseAdminController;
use WebEd\Base\Core\Support\DataTable\DataTables;
use WebEd\Base\ModulesManagement\Repositories\Contracts\PluginsRepositoryContract;
use WebEd\Base\ModulesManagement\Repositories\PluginsRepository;

class PluginsController extends BaseAdminController
{
    protected $module = 'webed-modules-management';

    protected $dashboardMenuId = 'webed-plugins';

    /**
     * @param PluginsRepository $repository
     */
    public function __construct(PluginsRepositoryContract $repository)
    {
        parent::__construct();

        $this->repository = $repository;
    }

    /**
     * Get index page
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getIndex()
    {
        $this->breadcrumbs->addLink('Plugins');

        $this->assets->addJavascripts('jquery-datatables');

        $this->setPageTitle('Plugins');

        $this->getDashboardMenu($this->dashboardMenuId);

        $this->dis['dataTableColumns'] = [
            'headings' => [
                ['name' => 'Name', 'width' => '20%'],
                ['name' => 'Description', 'width' => '50%'],
                ['name' => 'Actions', 'width' => '30%'],
            ],
        ];

        $this->dis['dataTableHeadings'] = json_encode([
            ['data' => 'name', 'name' => 'name', 'searchable' => false, 'orderable' => false],
            ['data' => 'description', 'name' => 'description', 'searchable' => false, 'orderable' => false],
            ['data' => 'actions', 'name' => 'actions', 'searchable' => false, 'orderable' => false],
        ]);

        return do_filter('webed-modules-plugin.index.get', $this)->viewAdmin('plugins-list');
    }

    /**
     * Set data for DataTable plugin
     * @param DataTables $dataTable
     * @return \Illuminate\Http\JsonResponse
     */
    public function postListing(DataTables $dataTable)
    {
        $modules = modules_management()->export('plugins');

        $data = $dataTable
            ->of($modules->values())
            ->editColumn('description', function ($item) {
                return array_get($item, 'description') . '<br><br>'
                . 'Author: ' . array_get($item, 'author') . '<br>'
                . 'Version: <b>' . array_get($item, 'version', '...') . '</b>';
            })
            ->addColumn('actions', function ($item) {
                $activeBtn = (!array_get($item, 'enabled')) ? form()->button('Active', [
                    'title' => 'Active this plugin',
                    'data-ajax' => route('admin::plugins.change-status.post', [
                        'module' => array_get($item, 'alias'),
                        'status' => 1,
                    ]),
                    'data-method' => 'POST',
                    'data-toggle' => 'confirmation',
                    'class' => 'btn btn-outline green btn-sm ajax-link',
                ]) : '';

                $disableBtn = (array_get($item, 'enabled')) ? form()->button('Disable', [
                    'title' => 'Disable this plugin',
                    'data-ajax' => route('admin::plugins.change-status.post', [
                        'module' => array_get($item, 'alias'),
                        'status' => 0,
                    ]),
                    'data-method' => 'POST',
                    'data-toggle' => 'confirmation',
                    'class' => 'btn btn-outline yellow-lemon btn-sm ajax-link',
                ]) : '';

                $installBtn = (array_get($item, 'enabled') && !array_get($item, 'installed')) ? form()->button('Install', [
                    'title' => 'Install this plugin\'s dependencies',
                    'data-ajax' => route('admin::plugins.install.post', [
                        'module' => array_get($item, 'alias'),
                    ]),
                    'data-method' => 'POST',
                    'data-toggle' => 'confirmation',
                    'class' => 'btn btn-outline blue btn-sm ajax-link',
                ]) : '';

                $uninstallBtn = (array_get($item, 'enabled') && array_get($item, 'installed')) ? form()->button('Uninstall', [
                    'title' => 'Uninstall this plugin\'s dependencies',
                    'data-ajax' => route('admin::plugins.uninstall.post', [
                        'module' => array_get($item, 'alias'),
                    ]),
                    'data-method' => 'POST',
                    'data-toggle' => 'confirmation',
                    'class' => 'btn btn-outline red-sunglo btn-sm ajax-link',
                ]) : '';

                return $activeBtn . $disableBtn . $installBtn . $uninstallBtn;
            });

        return do_filter('datatables.webed-modules-plugin.index.post', $data, $this)
            ->make(true);
    }

    public function postChangeStatus($module, $status)
    {
        switch ((bool)$status) {
            case true:
                return modules_management()->enableModule($module)->refreshComposerAutoload();
                break;
            default:
                return modules_management()->disableModule($module)->refreshComposerAutoload();
                break;
        }
    }

    public function postInstall($alias)
    {
        $module = get_module_information($alias);

        if(!$module) {
            return response_with_messages('Plugin not exists', true, 500);
        }

        \Artisan::call('module:install', [
            'alias' => $alias
        ]);

        return response_with_messages('Installed plugin dependencies');
    }

    public function postUninstall($alias)
    {
        $module = get_module_information($alias);

        if(!$module) {
            return response_with_messages('Plugin not exists', true, 500);
        }

        \Artisan::call('module:uninstall', [
            'alias' => $alias
        ]);

        return response_with_messages('Uninstalled plugin dependencies');
    }
}
