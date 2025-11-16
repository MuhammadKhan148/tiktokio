<?php
// Simple router for PHP built-in server
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Handle robots.txt
if ($requestPath === '/robots.txt') {
    require __DIR__ . '/robots.php';
    exit;
}

// Handle sitemap.xml
if ($requestPath === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

// Redirect root to /yt1s (make updated frontend the homepage)
if ($requestPath === '/') {
	header('Location: /yt1s/', true, 302);
	exit;
}

if (!function_exists('yt_frontend_bridge_script')) {
	function yt_frontend_bridge_script(): string
	{
		static $script = null;
		if ($script !== null) {
			return $script;
		}

		require_once __DIR__ . '/includes/yt_frontend.php';
		$manifestJson = json_encode(yt_frontend_js_manifest(), JSON_UNESCAPED_UNICODE);

		ob_start();
		?>
<script>
window.__YT_FRONTEND_MANIFEST = <?= $manifestJson ?>;
(function () {
	const API_URL = '/yt_frontend_api.php';
	const PAGE_KEY = (function () {
		var path = (window.location.pathname || '').toLowerCase();
		if (path.indexOf('youtube-to-mp3') !== -1) {
			return 'mp3';
		}
		if (path.indexOf('youtube-to-mp4') !== -1) {
			return 'mp4';
		}
		return 'home';
	})();
	const MANIFEST = window.__YT_FRONTEND_MANIFEST || {};
	const PAGE_MANIFEST = MANIFEST[PAGE_KEY] || { mode: 'data_i18n', fields: {} };
	let selectedLanguage = (localStorage.getItem('yt_frontend_lang') || 'en').toLowerCase();
	const cache = {};
	let languages = [];
	const languageMenu = document.getElementById('languageMenu');
	const languageToggle = document.getElementById('languageToggle');
	let menuVisible = false;

	function detectLanguageName(code) {
		code = (code || '').toLowerCase();
		for (var i = 0; i < languages.length; i += 1) {
			var lang = languages[i];
			if ((lang.code || '').toLowerCase() === code) {
				return lang.name || lang.code || 'Language';
			}
		}
		return code.toUpperCase() || 'Language';
	}

	function setToggleLabel(label) {
		if (!languageToggle) {
			return;
		}
		languageToggle.textContent = label || detectLanguageName(selectedLanguage);
	}

	function closeMenu() {
		if (!languageMenu) {
			return;
		}
		languageMenu.classList.remove('show');
		menuVisible = false;
	}

	function toggleMenu() {
		if (!languageMenu) {
			return;
		}
		menuVisible = !menuVisible;
		if (menuVisible) {
			languageMenu.classList.add('show');
		} else {
			languageMenu.classList.remove('show');
		}
	}

	function bindMenuBehavior() {
		if (!languageToggle || !languageMenu) {
			return;
		}
		languageToggle.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			toggleMenu();
		});
		document.addEventListener('click', function (event) {
			if (!languageMenu.contains(event.target) && event.target !== languageToggle) {
				closeMenu();
			}
		});
	}

	function renderLanguageMenu() {
		if (!languageMenu) {
			return;
		}
		languageMenu.innerHTML = '';
		languages.forEach(function (lang) {
			var option = document.createElement('div');
			option.className = 'language-item';
			option.dataset.lang = lang.code;
			option.textContent = lang.name;
			if ((lang.code || '').toLowerCase() === selectedLanguage) {
				option.classList.add('active');
			}
			option.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				closeMenu();
				loadLanguage((lang.code || 'en').toLowerCase());
			});
			languageMenu.appendChild(option);
		});
		highlightActiveLanguage();
	}

	function readExistingLanguages() {
		var list = [];
		if (!languageMenu) {
			return list;
		}
		languageMenu.querySelectorAll('.language-item').forEach(function (item) {
			var code = (item.getAttribute('data-lang') || 'en').toLowerCase();
			var name = item.textContent.trim() || code.toUpperCase();
			list.push({ id: null, code: code, name: name, is_default: false });
		});
		return list;
	}

	function highlightActiveLanguage() {
		if (!languageMenu) {
			return;
		}
		languageMenu.querySelectorAll('.language-item').forEach(function (node) {
			var code = (node.getAttribute('data-lang') || '').toLowerCase();
			if (code === selectedLanguage) {
				node.classList.add('active');
			} else {
				node.classList.remove('active');
			}
		});
	}

	async function fetchLanguages() {
		try {
			var response = await fetch(API_URL + '?action=languages', { credentials: 'same-origin' });
			if (!response.ok) {
				throw new Error('languages');
			}
			var payload = await response.json();
			languages = Array.isArray(payload.languages) && payload.languages.length ? payload.languages : readExistingLanguages();
		} catch (error) {
			languages = readExistingLanguages();
		}
		if (!languages.length) {
			languages = [{ id: 0, code: 'en', name: 'English', is_default: true }];
		}
		renderLanguageMenu();
		bindMenuBehavior();
		setToggleLabel(detectLanguageName(selectedLanguage));
	}

	async function loadLanguage(code) {
		selectedLanguage = (code || 'en').toLowerCase();
		localStorage.setItem('yt_frontend_lang', selectedLanguage);
		await fetchContent(selectedLanguage);
		highlightActiveLanguage();
	}

	async function fetchContent(code) {
		cache[PAGE_KEY] = cache[PAGE_KEY] || {};
		if (cache[PAGE_KEY][code]) {
			applyContent(cache[PAGE_KEY][code]);
			return;
		}
		try {
			var response = await fetch(API_URL + '?action=content&page=' + encodeURIComponent(PAGE_KEY) + '&lang=' + encodeURIComponent(code), { credentials: 'same-origin' });
			if (!response.ok) {
				throw new Error('content');
			}
			var payload = await response.json();
			cache[PAGE_KEY][code] = payload;
			applyContent(payload);
		} catch (error) {
			console.error('YT front content error', error);
		}
	}

	function applyContent(payload) {
		if (!payload || typeof payload !== 'object') {
			return;
		}
		applyFields(payload.strings || {});
		var label = payload.language && payload.language.name ? payload.language.name : detectLanguageName(selectedLanguage);
		setToggleLabel(label);
	}

	function applyFields(strings) {
		var fields = PAGE_MANIFEST.fields || {};
		Object.keys(fields).forEach(function (key) {
			if (strings[key] === undefined || strings[key] === null) {
				return;
			}
			applyFieldValue(key, strings[key], fields[key]);
		});
		if (PAGE_MANIFEST.mode === 'data_i18n') {
			document.querySelectorAll('[data-i18n]').forEach(function (node) {
				var key = node.getAttribute('data-i18n');
				if (!key || fields[key] !== undefined) {
					return;
				}
				if (strings[key] === undefined) {
					return;
				}
				var renderType = node.tagName === 'INPUT' ? 'placeholder' : 'text';
				setNodeValue(node, strings[key], renderType, renderType === 'placeholder' ? 'placeholder' : null);
			});
		}
	}

	function applyFieldValue(key, value, definition) {
		if (!definition) {
			return;
		}
		var renderType = definition.render || 'text';
		if (renderType === 'meta_title') {
			document.title = value;
			return;
		}
		if (renderType === 'meta_description') {
			var meta = document.querySelector('meta[name="description"]');
			if (!meta) {
				meta = document.createElement('meta');
				meta.name = 'description';
				document.head.appendChild(meta);
			}
			meta.setAttribute('content', value);
			return;
		}
		var targets = [];
		if (definition.selector) {
			targets = document.querySelectorAll(definition.selector);
		} else if (PAGE_MANIFEST.mode === 'data_i18n') {
			targets = document.querySelectorAll('[data-i18n="' + key + '"]');
		}
		if (!targets.length && definition.attribute === 'placeholder' && PAGE_MANIFEST.mode !== 'data_i18n') {
			targets = document.querySelectorAll('[data-i18n="' + key + '"]');
		}
		targets.forEach(function (node) {
			setNodeValue(node, value, renderType, definition.attribute || null);
		});
	}

	function setNodeValue(node, value, renderType, attribute) {
		if (!node) {
			return;
		}
		if (renderType === 'html') {
			node.innerHTML = value;
			return;
		}
		if (renderType === 'placeholder') {
			node.setAttribute('placeholder', value);
			return;
		}
		if (attribute) {
			node.setAttribute(attribute, value);
			return;
		}
		if (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') {
			node.value = value;
		} else {
			node.textContent = value;
		}
	}

	function blockInspect() {
		document.addEventListener('contextmenu', function (event) {
			event.preventDefault();
		});
		document.addEventListener('keydown', function (event) {
			var key = (event.key || '').toUpperCase();
			if (event.keyCode === 123 || key === 'F12') {
				event.preventDefault();
			}
			if (event.ctrlKey && event.shiftKey && ['I', 'J', 'C'].indexOf(key) !== -1) {
				event.preventDefault();
			}
			if (event.ctrlKey && key === 'U') {
				event.preventDefault();
			}
		});
	}

	function wireSearch() {
		var input = document.querySelector('.search-input');
		var button = document.querySelector('.convert-btn');
		function submitQuery() {
			if (!input) {
				return;
			}
			var query = (input.value || '').trim();
			if (!query) {
				alert('Please enter a link or keyword.');
				return;
			}
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = '/search.php';
			form.style.display = 'none';
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'query';
			hidden.value = query;
			form.appendChild(hidden);
			document.body.appendChild(form);
			form.submit();
		}
		if (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				submitQuery();
			});
		}
		if (input) {
			input.addEventListener('keydown', function (event) {
				if (event.key === 'Enter') {
					event.preventDefault();
					submitQuery();
				}
			});
		}
	}

	async function bootstrap() {
		blockInspect();
		wireSearch();
		await fetchLanguages();
		await loadLanguage(selectedLanguage);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootstrap);
	} else {
		bootstrap();
	}
})();
</script>
<?php

		$script = ob_get_clean();
		return str_replace('</script>', '<\/script>', $script);
	}
}

// Mount updated_frontend at /yt1s without modifying its files
if ($requestPath === '/yt1s' || $requestPath === '/yt1s/') {
	$ytBase = __DIR__ . '/updated_frontend/client_frontend';
	$index = $ytBase . '/index.html';
	if (file_exists($index)) {
		header('Content-Type: text/html; charset=utf-8');
		$html = file_get_contents($index);
		$bridge = yt_frontend_bridge_script();
		if (stripos($html, '</body>') !== false) {
			$html = str_ireplace('</body>', $bridge . '</body>', $html);
		} else {
			$html .= $bridge;
		}
		echo $html;
		exit;
	}
	http_response_code(404);
	exit('Not found');
}
if (strpos($requestPath, '/yt1s/') === 0) {
	$ytBase = __DIR__ . '/updated_frontend/client_frontend';
	$relative = substr($requestPath, strlen('/yt1s/'));
	if ($relative === '' || substr($relative, -1) === '/') {
		$relative .= 'index.html';
	}
	$path = $ytBase . '/' . $relative;
	if (file_exists($path) && is_file($path)) {
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		switch ($ext) {
			case 'html':
			case 'htm':
				$mime = 'text/html; charset=utf-8'; break;
			case 'css':
				$mime = 'text/css; charset=utf-8'; break;
			case 'js':
				$mime = 'application/javascript; charset=utf-8'; break;
			case 'png':
				$mime = 'image/png'; break;
			case 'jpg':
			case 'jpeg':
				$mime = 'image/jpeg'; break;
			case 'webp':
				$mime = 'image/webp'; break;
			case 'svg':
				$mime = 'image/svg+xml'; break;
			case 'ico':
				$mime = 'image/x-icon'; break;
			default:
				$mime = 'application/octet-stream';
		}
		header('Content-Type: ' . $mime);
		if ($mime === 'text/html; charset=utf-8') {
			// Inject the same bridge for sub HTML pages under /yt1s
			$html = file_get_contents($path);
			$bridge = yt_frontend_bridge_script();
			if (stripos($html, '</body>') !== false) {
				$html = str_ireplace('</body>', $bridge . '</body>', $html);
			} else {
				$html .= $bridge;
			}
			echo $html;
		} else {
			readfile($path);
		}
		exit;
	}
	http_response_code(404);
	exit('Not found');
}

// Translation API endpoints expected by updated_frontend
if ($requestPath === '/yt_frontend_api.php') {
	require __DIR__ . '/yt_frontend_api.php';
	exit;
}
if ($requestPath === '/translate') {
	require __DIR__ . '/api/translate.php';
	exit;
}
if ($requestPath === '/translate/batch') {
	require __DIR__ . '/api/translate_batch.php';
	exit;
}

// YouTube download API endpoints
if ($requestPath === '/api_search.php') {
	require __DIR__ . '/api_search.php';
	exit;
}
if ($requestPath === '/api_download.php') {
	require __DIR__ . '/api_download.php';
	exit;
}

// Serve static files if they exist
$filePath = __DIR__ . $requestPath;
if ($requestPath !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false; // Let PHP serve the file
}

// Don't route admin requests
if (strpos($requestPath, '/admin/') === 0) {
    return false; // Let PHP serve admin files
}

// Route everything else to router.php
require __DIR__ . '/router.php';
