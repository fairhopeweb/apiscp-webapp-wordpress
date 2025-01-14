<?php
	/**
 * Copyright (C) Apis Networks, Inc - All Rights Reserved.
 *
 * Unauthorized copying of this file, via any medium, is
 * strictly prohibited without consent. Any dissemination of
 * material herein is prohibited.
 *
 * For licensing inquiries email <licensing@apisnetworks.com>
 *
 * Written by Matt Saladna <matt@apisnetworks.com>, August 2020
 */

	namespace Module\Support\Webapps\App\Type\Wordpress;

	use Module\Support\Webapps\App\Type\Unknown\Handler as Unknown;
	use function is_array;

	class Handler extends Unknown
	{
		const NAME = 'WordPress';
		const ADMIN_PATH = '/wp-admin';
		const LINK = 'https://wordpress.org/';

		const DEFAULT_FORTIFICATION = 'max';
		const FEAT_ALLOW_SSL = true;
		const FEAT_RECOVERY = true;

		const TRANSIENT_RECONFIGURABLES = [
			'migrate', 'duplicate', 'debug', 'language'
		];

		public function recover(): bool
		{
			if (!$this->wordpress_disable_all_plugins($this->hostname, $this->path)) {
				return warn('failed to disable all plugins');
			}

			$themes = $this->wordpress_theme_status($this->hostname, $this->path);
			$filtered = array_filter($themes, static function ($v, $k) {
				if (!$v['current']) {
					return false;
				}
				if (0 !== strncmp($k, 'twenty', 6)) {
					return false;
				}

				return true;
			}, ARRAY_FILTER_USE_BOTH);
			if ($filtered) {
				$theme = key($filtered);

				return $this->wordpress_enable_theme($this->hostname, $this->path, $theme) &&
					info('Theme reset to %s', $theme);
			}

			return true;
		}

		private function installPlugins($plugins): void
		{
			if (!is_array($plugins)) {
				$plugins = (array)$plugins;
			}
			foreach ($plugins as $plugin) {
				if ($this->wordpress_install_plugin($this->hostname, $this->path, $plugin)) {
					info("installed plugin `%s'", $plugin);
				}
			}
		}

		public function changePassword(string $password): bool
		{
			return $this->wordpress_change_admin($this->hostname, $this->path, ['user_pass' => $password]);
		}

		public function handle(array $params): bool
		{
			if (isset($params['render'])) {
				\Lararia\Bootstrapper::minstrap();
				echo view('@webapp(wordpress)::partials.plugin-table', ['app' => $this]);
				exit;
			} else if (isset($params['sso-check'])) {
				\Lararia\Bootstrapper::minstrap();
				echo view('@webapp(wordpress)::partials.actions.sso', ['app' => $this]);
				exit;
			}
			if (isset($params['install-package'])) {
				return $this->wordpress_install_package($params['install-package']);
			}

			if (isset($params['uninstall-package'])) {
				return $this->wordpress_uninstall_package($params['uninstall-package']);
			}

			if (isset($params['enable-sso']) || isset($params['install-sso'])) {
				$sso = Sso::instantiateContexted($this->getAuthContext(), [$this]);
				if (isset($params['install-sso'])) {
					if (!$this->wordpress_install_package($params['install-sso'])) {
						return false;
					}
					defer($_, static function () use ($sso) {
						$sso->handle();
					});
				}
				// override Y/n prompt
				return $sso->enable();
			}
			if (isset($params['wordpress-sso'])) {
				return  Sso::instantiateContexted($this->getAuthContext(), [$this])->handle();
			}

			return parent::handle($params);
		}
	}