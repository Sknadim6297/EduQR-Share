document.addEventListener('DOMContentLoaded', () => {
	const sidebar = document.querySelector('[data-app-sidebar]');
	const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
	const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
	const setSidebarOpen = (open) => {
		if (!sidebar || !sidebarToggle || !sidebarBackdrop) return;
		sidebar.classList.toggle('is-open', open);
		sidebarBackdrop.classList.toggle('is-visible', open);
		sidebarToggle.setAttribute('aria-expanded', String(open));
	};

	sidebarToggle?.addEventListener('click', () => setSidebarOpen(sidebarToggle.getAttribute('aria-expanded') !== 'true'));
	sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') setSidebarOpen(false);
	});

	document.querySelectorAll('[data-password-toggle]').forEach((button) => {
		button.addEventListener('click', () => {
			const password = document.querySelector('#password');
			if (!password) return;
			const show = password.type === 'password';
			password.type = show ? 'text' : 'password';
			button.textContent = show ? 'Hide' : 'Show';
			button.setAttribute('aria-pressed', String(show));
		});
	});

	document.querySelector('[data-login-form]')?.addEventListener('submit', (event) => {
		const button = event.currentTarget.querySelector('[data-login-submit]');
		const label = button?.querySelector('[data-login-label]');
		if (button && label) {
			button.disabled = true;
			label.textContent = 'Signing in…';
		}
	});

	document.querySelectorAll('[data-upload-form]').forEach((form) => {
		const input = form.querySelector('[data-file-input]');
		const summary = form.querySelector('[data-file-summary]');
		const dropPrompt = form.querySelector('[data-drop-prompt]');
		const feedback = form.querySelector('[data-upload-feedback]');
		const submitButton = form.querySelector('[data-submit-button]');
		const progressWrap = form.querySelector('[data-progress-wrap]');
		const progressBar = form.querySelector('[data-progress-bar]');
		const dropZone = form.querySelector('[data-drop-zone]');
		const maxBytes = Number(form.dataset.maxKb) * 1024;
		let submitting = false;

		if (!input) return;

		const readableSize = (bytes) => bytes < 1048576
			? `${Math.max(1, Math.round(bytes / 1024))} KB`
			: `${(bytes / 1048576).toFixed(2)} MB`;

		const setFile = (file) => {
			if (!summary || !feedback) return;
			feedback.textContent = '';
			summary.hidden = !file;
			if (dropPrompt) dropPrompt.hidden = Boolean(file);
			if (dropPrompt) dropPrompt.hidden = Boolean(file);

			if (!file) return;

			summary.textContent = `${file.name} · ${readableSize(file.size)} · ${file.type || 'File type checked on upload'}`;
			const extension = file.name.split('.').pop().toLowerCase();
			if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) {
				feedback.textContent = 'Choose a PDF, JPG, JPEG, or PNG file.';
			} else if (file.size > maxBytes) {
				feedback.textContent = `Choose a file under ${readableSize(maxBytes)}.`;
			}
		};

		input.addEventListener('change', () => setFile(input.files[0]));

		if (dropZone) {
			['dragenter', 'dragover'].forEach((eventName) => dropZone.addEventListener(eventName, (event) => {
				event.preventDefault();
				dropZone.classList.add('is-dragging');
			}));
			['dragleave', 'drop'].forEach((eventName) => dropZone.addEventListener(eventName, (event) => {
				event.preventDefault();
				dropZone.classList.remove('is-dragging');
			}));
			dropZone.addEventListener('drop', (event) => {
				const [file] = event.dataTransfer.files;
				if (!file) return;
				const transfer = new DataTransfer();
				transfer.items.add(file);
				input.files = transfer.files;
				setFile(file);
			});
		}

		form.addEventListener('submit', (event) => {
			if (submitting) {
				event.preventDefault();
				return;
			}

			const file = input.files[0];
			if (!file) return;
			if (file.size > maxBytes || (feedback && feedback.textContent)) {
				event.preventDefault();
				if (feedback && !feedback.textContent) feedback.textContent = 'Choose a supported file.';
				return;
			}

			event.preventDefault();
			submitting = true;
			if (submitButton) {
				submitButton.disabled = true;
				submitButton.dataset.originalText = submitButton.textContent;
				submitButton.textContent = 'Uploading…';
			}
			if (progressWrap) {
				progressWrap.hidden = false;
				progressWrap.setAttribute('aria-valuenow', '0');
			}
			if (feedback) feedback.textContent = 'Uploading your document…';

			const request = new XMLHttpRequest();
			request.open(form.method || 'POST', form.action);
			request.setRequestHeader('Accept', 'application/json');
			request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			request.upload.addEventListener('progress', (progress) => {
				if (progress.lengthComputable && progressBar) {
					const percentage = Math.round((progress.loaded / progress.total) * 100);
					progressBar.style.width = `${percentage}%`;
					progressWrap?.setAttribute('aria-valuenow', String(percentage));
				}
			});
			request.addEventListener('load', () => {
				let response = {};
				try { response = JSON.parse(request.responseText); } catch { /* Invalid responses get a generic message. */ }

				if (request.status >= 200 && request.status < 300 && response.redirect) {
					window.location.assign(response.redirect);
					return;
				}

				const firstError = response.errors ? Object.values(response.errors).flat()[0] : null;
				if (feedback) feedback.textContent = firstError || response.message || 'The upload could not be completed. Please try again.';
				submitting = false;
				if (submitButton) {
					submitButton.disabled = false;
					submitButton.textContent = submitButton.dataset.originalText || 'Try again';
				}
				if (progressWrap) progressWrap.hidden = true;
			});
			request.addEventListener('error', () => {
				if (feedback) feedback.textContent = 'Network interrupted. Check your connection and try again.';
				submitting = false;
				if (submitButton) {
					submitButton.disabled = false;
					submitButton.textContent = submitButton.dataset.originalText || 'Try again';
				}
				if (progressWrap) progressWrap.hidden = true;
			});
			request.send(new FormData(form));
		});
	});

	document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
		if (!window.confirm(form.dataset.confirm)) event.preventDefault();
	}));

	const feedback = document.querySelector('[data-share-feedback]');
	const copyLink = async (url) => {
		try {
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(url);
			} else {
				const temporary = document.createElement('textarea');
				temporary.value = url;
				temporary.setAttribute('readonly', '');
				temporary.style.position = 'fixed';
				temporary.style.opacity = '0';
				document.body.append(temporary);
				temporary.select();
				const copied = document.execCommand('copy');
				temporary.remove();
				if (!copied) throw new Error('Copy was not available');
			}
			if (feedback) feedback.textContent = 'Link copied to clipboard.';
			return true;
		} catch {
			if (feedback) feedback.textContent = 'Copy was unavailable. Select the link above to copy it manually.';
			return false;
		}
	};
	const downloadQrImage = (button) => {
		const download = document.createElement('a');
		download.href = button.dataset.qrDownloadUrl;
		download.download = button.dataset.qrFilename || 'schoolqr-document.png';
		download.hidden = true;
		document.body.append(download);
		download.click();
		download.remove();
	};
	const useShareFallback = async (button, url, message) => {
		downloadQrImage(button);
		const copied = await copyLink(url);
		if (feedback) {
			feedback.textContent = copied
				? `${message} A QR download was requested and the document link was copied.`
				: `${message} A QR download was requested. Copy the document link from the field above.`;
		}
	};

	document.querySelectorAll('[data-copy-link]').forEach((button) => button.addEventListener('click', () => {
		const url = button.dataset.shareUrl || document.querySelector('[data-share-url]')?.value;
		if (url) copyLink(url);
	}));

	document.querySelectorAll('[data-native-share]').forEach((button) => button.addEventListener('click', async () => {
		const url = button.dataset.shareUrl;
		const qrUrl = button.dataset.qrUrl;
		if (!url || !qrUrl || button.disabled) return;

		const originalLabel = button.innerHTML;
		button.disabled = true;
		button.textContent = 'Preparing QR image…';
		if (feedback) feedback.textContent = 'Loading the existing QR image for sharing…';

		try {
			if (typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function') {
				await useShareFallback(button, url, 'This browser cannot share files with other apps.');
				return;
			}

			const response = await fetch(qrUrl, {
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'image/png' },
			});

			if (response.status === 404 || response.status === 410) {
				if (feedback) feedback.textContent = 'This document is no longer available. Return to your library to check its status.';
				return;
			}

			if (response.redirected && new URL(response.url).pathname.includes('/login')) {
				if (feedback) feedback.textContent = 'Your teacher session expired. Sign in again to share or download this QR code.';
				return;
			}

			if (!response.ok || !response.headers.get('Content-Type')?.toLowerCase().startsWith('image/png')) {
				throw new Error(`QR image request failed with status ${response.status}.`);
			}

			const imageBlob = await response.blob();
			if (imageBlob.size === 0 || !imageBlob.type.toLowerCase().startsWith('image/png')) {
				throw new Error('The QR image response was empty or invalid.');
			}

			const qrFile = new File([imageBlob], button.dataset.qrFilename || 'schoolqr-document.png', {
				type: 'image/png',
				lastModified: Date.now(),
			});
			const title = button.dataset.shareTitle || 'Shared classroom document';
			const shareData = {
				title,
				text: `${title}\n${url}`,
				url,
				files: [qrFile],
			};

			if (!navigator.canShare({ files: [qrFile] }) || !navigator.canShare(shareData)) {
				await useShareFallback(button, url, 'File sharing is not supported by this browser.');
				return;
			}

			try {
				await navigator.share(shareData);
				if (feedback) feedback.textContent = 'QR image and document link shared.';
			} catch (error) {
				if (error.name === 'AbortError') {
					if (feedback) feedback.textContent = 'Sharing cancelled. No file was sent.';
					return;
				}

				if (feedback) feedback.textContent = 'Sharing failed. Use Download QR image and Copy link instead.';
			}
		} catch (error) {
			await useShareFallback(button, url, 'Could not prepare the QR image for sharing.');
		} finally {
			button.disabled = false;
			button.innerHTML = originalLabel;
		}
	}));
});
