@if (count(LaravelLocalization::getSupportedLocales()) > 1)
	<!-- Language Selector -->
	<li class="language-links">
		<ul class="responsive">
			@foreach(LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
				@if (strtolower($localeCode) != strtolower(config('app.locale')))
					<?php
						// Controller Parameters
						$attr = [];
						$attr['countryCode'] = config('country.icode');
						if (isset($uriPathCatSlug)) {
							$attr['catSlug'] = $uriPathCatSlug;
							if (isset($uriPathSubCatSlug)) {
								$attr['subCatSlug'] = $uriPathSubCatSlug;
							}
						}
						if (isset($uriPathCityName) && isset($uriPathCityId)) {
							$attr['city'] = $uriPathCityName;
							$attr['id'] = $uriPathCityId;
						}
						if (isset($uriPathUserId)) {
							$attr['id'] = $uriPathUserId;
							if (isset($uriPathUsername)) {
								$attr['username'] = $uriPathUsername;
							}
						}
						if (isset($uriPathUsername)) {
							if (isset($uriPathUserId)) {
								$attr['id'] = $uriPathUserId;
							}
							$attr['username'] = $uriPathUsername;
						}
						if (isset($uriPathTag)) {
							$attr['tag'] = $uriPathTag;
						}
						if (isset($uriPathPageSlug)) {
							$attr['slug'] = $uriPathPageSlug;
						}
						if (\Illuminate\Support\Str::contains(\Route::currentRouteAction(), 'Post\DetailsController')) {
							$attr['slug'] = getSegment(1);
							$attr['id'] = getSegment(2);
						}
						// $attr['debug'] = '1';
						// $link = LaravelLocalization::getLocalizedURL($localeCode, null, $attr);
						$link = lurl(null, $attr, $localeCode);
						$countryName = strtoupper($localeCode);
						$localeCode = strtolower($localeCode);
						
					?>
					<li class="nav-item">
						<a href="{{ $link }}" tabindex="-1" rel="alternate" hreflang="{{ $localeCode }}">
							<div>
								<img src="/images/flags/32/{{{ $localeCode }}}.png"  />
							</div>
							<div class="language-name">{{ $countryName }}</div>
						</a>
					</li>
				@endif
			@endforeach
		</ul>
	</li>
@endif