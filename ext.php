<?php

namespace nekstati\schedulePost;

class ext extends \phpbb\extension\base
{
	public function enable_step($old_state)
	{
		static $is_ext_installed;
		$is_ext_installed = ($is_ext_installed ?? $this->container->get('ext.manager')->is_configured('nekstati/schedulePost'));

		$is_first_install_step = (!$is_ext_installed && $old_state === false);

		if ($is_first_install_step && !isset($_POST['continue']))
		{
			$this->show_preinstall_page();
		}

		$current_state = parent::enable_step($old_state);
		$is_last_install_step = (!$is_ext_installed && $old_state !== false && $current_state === false);

		if ($is_last_install_step)
		{
			// Postinstall code
			// ...
			// Seems to be impossible to show custom page here, since we must return $current_state, otherwise the extension stays installed but not enabled.
			// At least I failed. :) Maybe you'll find the way to do this.
			// The only I found for now is that nasty thing:
			// ...
			// $language = $this->container->get('language');
			// $language->add_lang('install', 'nekstati/schedulePost');
			// $text = $language->lang('SCHEDULE_POST_GO_TO_EXT_SETTINGS');
			// $url = '...';
			// $this->container->get('template')->append_var('L_ADMIN_PANEL',
				// "<script>
					// document.addEventListener('DOMContentLoaded', () => {
						// let a = document.querySelector('.successbox a');
						// if (!a) return;
						// a.href = '$url';
						// a.textContent = '$text';
					// });
				// </script>");
		}

		return $current_state;
	}

	private function show_preinstall_page()
	{
		try
		{
			$current_url = str_replace('&', '&amp;', $this->container->get('user')->page['page']);
			$current_url = $this->container->get('path_helper')->get_valid_page($current_url, true);
			$current_url = reapply_sid($current_url);

			$language = $this->container->get('language');
			$language->add_lang('install', 'nekstati/schedulePost');

			$this->container->get('template.twig.loader')->addPath('./../ext/nekstati/schedulePost/adm/style/', 'nekstati_schedulePost');
			$template = $this->container->get('template');
			$template->assign_var('U_ACTION', $current_url);
			$template->set_filenames(['body' => '@nekstati_schedulePost/preinstall.html']);
			adm_page_header($language->lang('SCHEDULE_POST_INSTALL_TITLE'));
			adm_page_footer();
		}
		catch (\Exception $e)
		{
		}
	}
}
