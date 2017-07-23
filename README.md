# CurrentLevel

**CurrentLevel** is a plugin for Pico CMS <http://picocms.org> that only parses the directory in which
the currently requested file resides while building the auto-generated menu.

In addition, **CurrentLevel** adds a (very) basic twig function to generate breadcrumbs.

(And it works with [PicoEdit](https://github.com/blocknotes/pico_edit) ;) )

Live demo: <http://blog.pixelwoelkchen.de>

## Credits

This plugin is based on @bigicoin's [PicoTooManyPages](https://github.com/bigicoin/PicoTooManyPages).

## Installation

Simply drop `CurrentLevel` into Pico CMS's `plugin` directory. Then open
`config/config.php` and add:


```php
$config[ 'CurrentLevel.enabled' ] = true;
```

## Effect in templates

``{{ pages }}`` now only holds the files that are in the same (sub)directory as the currently
requested file. In addition if there are any subdirectories with an `index.md` those will be
contained as well.

Example:

```
/index.md
/about.md
/contact.md
/blog/index.md
/blog.../
/archive/index.md
/archive/2014
/archive/2014/..
/archive/2015/index.md
/archive/2015/..
/archive/2016/index.md
/archive/2016/..
/archive/2017/index.md
/archive/2017/..
```

If `https://your.page.domain/contact` is called, `{{ pages }` will hold `index`, `about`,
`contact`, `blog` and `archive` (because the latter subdirectories contain `index.md` files).

If `https://your.page.domain/archive` is called, `{{ pages }` will hold `archive/index`,
`archive/2015`, `archive/2016` and `archive/2017` but not `archive/2014`.

If you don't want the current level's index file to appear use something along:

```twig
{% set currentpath = current_page.id|split('/') %}
{% for page in pages if page.title %}
	{% set pagepath = page.id|split('/') %}
	{% if pagepath|last != 'index' or pagepath|length != currentpath|length %}
		<a href="{{ page.url }}">{{ page.title }}</a>
	{% endif %}
{% endfor %}
```

## Using breadcrumbs

A crude example:

```twig
<nav role="navigation">
	<ul>
		<li>
			<a href="{{ base_url }}">Start</a>

				{% set crumbs = breadcrumbs(current_page.id) %}

				{% for foldername, folder in crumbs %}
					<ul>
						<li>
							<a href="{{ base_url }}?{{ folder }}">{{ foldername }}</a>
				{% endfor %}

				<ul>

				{% for page in pages if page.title %}
					<li class="{% if page.id == current_page.id %}active{% endif %}">
						<a href="{{ page.url }}" data-shortcut="{{page.meta.shortcut}}">
							{{ page.title }}
							{% if page.meta.shortcut %}
								<span class="shortcut">{{page.meta.shortcut|replace({'+':' '})}}</a>
							{% endif %}
						</a>
					</li>
				{% endfor %}

				</ul>

				{% for folder in crumbs %}
						</li>
					</ul>
				{% endfor %}

		</li>
</nav>
```

If you don't want to include the current level's `index.md` (because it's included in the
breadcrumbs):

```twig
<nav role="navigation">
	<ul>
		<li>
			<a href="{{ base_url }}">Start</a>

				{% set crumbs = breadcrumbs(current_page.id) %}
				{% set currentpath = current_page.id|split('/') %}

				{% for foldername, folder in crumbs %}
					<ul>
						<li>
							<a href="{{ base_url }}?{{ folder }}">{{ foldername }}</a>
				{% endfor %}

				<ul>

				{% for page in pages if page.title %}
					{% set pagepath = page.id|split('/') %}
					{% if pagepath|last != 'index' or pagepath|length != currentpath|length %}
					<li class="{% if page.id == current_page.id %}active{% endif %}">
						<a href="{{ page.url }}" data-shortcut="{{page.meta.shortcut}}">
							{{ page.title }}
							{% if page.meta.shortcut %}
								<span class="shortcut">{{page.meta.shortcut|replace({'+':' '})}}</a>
							{% endif %}
						</a>
					</li>
					{% endif %}

				{% endfor %}

				</ul>

				{% for folder in crumbs %}
						</li>
					</ul>
				{% endfor %}

		</li>
</nav>
```
