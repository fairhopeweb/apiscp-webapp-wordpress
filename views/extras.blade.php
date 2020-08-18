<div id="packageManager" class="collapse">
	<h5>Package management</h5>
	<p class="">
		<b>Reminder:</b> packages update automatically every Sunday and Wednesday.
	</p>
	<div id="packageTable">
		<h6>
			<i class="ui-ajax ui-ajax-indicator ui-ajax-loading"></i> Loading packages
		</h6>
	</div>
</div>

<script type="text/javascript">
	function fnFromMode(mode, type, checked) {
		var fn = 'wordpress_', tmp;
		if (mode === 'activate') {
			tmp = checked ? 'enable_' : 'disable_';
			tmp = tmp + (type === 'plugin' ? 'plugin' : 'theme');
			return fn + tmp;
		}

		return fn + (checked ? 'unskip_asset' : 'skip_asset');
	}

	$('#packageManager').on('change', ':input[data-asset]', function (event) {
		event.preventDefault();
		var checked = $(this).prop('checked');
		$(this).prop('checked', checked);
		var that = $(this), type = that.data('asset'), name = that.data('name'),
			mode = that.data('mode'), fn = fnFromMode(mode, type, checked), args;
		args = mode === 'activate' ? [__WA_META.hostname, __WA_META.path, name] : [__WA_META.hostname, __WA_META.path, name, type];
		return apnscp.cmd(fn, args, {useCustomHandlers: true}).done(function () {
			apnscp.addMessage({!! json_encode(_("Changes succeeded")) !!});
			that.prop('checked', checked);
		}).fail(function (xhr, textStatus, errorThrown) {
			apnscp.ajaxError(xhr, textStatus, errorThrown);
			that.prop('checked', !checked);
		});
	}).one('show.bs.collapse', function() {
		apnscp.render({render: 'wp-assets', hostname: __WA_META.hostname, path: __WA_META.path}, '').done(function (html) {
			$('#packageTable').html(html);
			var lastPopover;
			$('#packageManager [data-toggle="popover"]').popover().on('show.bs.popover', function () {
				if (lastPopover && lastPopover !== $(this)) {
					lastPopover.popover('hide');
				}
				lastPopover = $(this);
			}).on('show.bs.popover', function() {
				var that = $(this), chkurl,
					wpurl = 'https://wordpress.org/' + this.dataset.asset + 's/' + this.dataset.name;
				if (this.dataset.asset === 'plugin') {
					chkurl = {!! json_encode(\Wordpress_Module::PLUGIN_VERSION_CHECK_URL) !!};
				} else {
					chkurl = {!! json_encode(\Wordpress_Module::THEME_VERSION_CHECK_URL) !!};
				}
				chkurl = chkurl.replace(/%plugin%|%theme%/, this.dataset.name);
				$.ajax({
					dataType: "json",
					url: chkurl
				}).then(function(ret, status, jqxhr) {
					if (!ret || ret.error) {
						return $.Deferred().reject(ret.error || '');
					}
					return ret;
				}).done(function (ret, status, jqxhr) {
						$('.popover-content', that.data('bs.popover').tip).append(
							$('<a href="' + wpurl + '" class="d-block mt-2 ui-action-label ui-action-visit-site" target="wp">').
							text({!! json_encode(_("Visit on wordpress.org")) !!})
						);
				}).fail(function() {
					$('.popover-content', that.data('bs.popover').tip).append(
						[
							$('<span class="d-block mt-2 text-info">').
								text("💲 " + {!! json_encode(_('This is a commercial/third-party package')) !!})
						]
					);
				});
			});
		});
	});
</script>