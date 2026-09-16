/* assets/js/face_login.js - Strict 1-to-1 Account Owner Biometric Authentication */
(function () {
  'use strict';

  const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
  let modelsLoaded = false;
  let modelsLoading = false;
  let videoStream = null;
  let scanInterval = null;
  let isSubmitting = false;

  const video          = document.getElementById('videoInput');
  const captureBtn     = document.getElementById('captureBtn');
  const messageEl      = document.getElementById('faceLoginMessage');
  const modal          = document.getElementById('faceLoginModal');
  const targetBox      = document.getElementById('scanTargetBox');
  const modalUserInput = document.getElementById('modal_username_email');
  const pageUserInput  = document.getElementById('username_email');

  if (!video || !messageEl || !modal) return;

  function showMessage(msg, type) {
    type = type || 'info';
    messageEl.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-0 fw-semibold" role="alert">' + msg + '</div>';
  }

  // Preload models in background immediately on page load
  async function preloadModels() {
    if (modelsLoaded || modelsLoading) return;
    modelsLoading = true;
    try {
      if (typeof faceapi !== 'undefined') {
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
          faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]);
        modelsLoaded = true;
      }
    } catch (err) {
      console.warn('Biometric model load warning:', err);
    } finally {
      modelsLoading = false;
    }
  }

  preloadModels();

  async function ensureModels() {
    if (modelsLoaded) return true;
    showMessage('<i class="fa-solid fa-spinner fa-spin me-2"></i>Initializing biometric engine…', 'warning');
    await preloadModels();
    return modelsLoaded;
  }

  async function startCamera() {
    try {
      videoStream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          facingMode: 'user'
        }
      });
      video.srcObject = videoStream;
      await video.play();
      return true;
    } catch (err) {
      console.error('Webcam error:', err);
      showMessage('Camera access blocked. Please allow webcam permission in your browser.', 'danger');
      return false;
    }
  }

  function stopCamera() {
    if (scanInterval) {
      clearInterval(scanInterval);
      scanInterval = null;
    }
    if (videoStream) {
      videoStream.getTracks().forEach(function (t) { t.stop(); });
      videoStream = null;
    }
    video.srcObject = null;
    isSubmitting = false;
    resetBoxVisual();
  }

  function resetBoxVisual() {
    if (targetBox) {
      targetBox.className = 'border border-2 border-primary rounded-4';
      targetBox.style.boxShadow = '0 0 15px rgba(13, 110, 253, 0.4)';
    }
  }

  // Precise detector settings (320px input size matching registration encoding)
  const fastDetectorOptions = () => new faceapi.TinyFaceDetectorOptions({
    inputSize: 320,
    scoreThreshold: 0.5
  });

  function getActiveAccount() {
    var fromModal = modalUserInput ? modalUserInput.value.trim() : '';
    if (fromModal) return fromModal;
    var fromPage = pageUserInput ? pageUserInput.value.trim() : '';
    return fromPage;
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>'"]/g, function (tag) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag);
    });
  }

  // Strictly verify the live face ONLY against the recorded owner of the active account
  async function verifyDescriptor(descriptorArray) {
    if (isSubmitting) return;

    var account = getActiveAccount();
    if (!account) {
      showMessage('<i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Please enter your Username or Student ID first. Only the user of the account can be scanned.', 'warning');
      if (modalUserInput) modalUserInput.focus();
      return;
    }

    isSubmitting = true;

    if (targetBox) {
      targetBox.className = 'border border-3 border-info rounded-4';
      targetBox.style.boxShadow = '0 0 20px rgba(13, 202, 240, 0.8)';
    }

    showMessage('<i class="fa-solid fa-spinner fa-spin me-2"></i>Verifying face for account <strong>' + escapeHtml(account) + '</strong>…', 'info');

    var csrfEl = document.querySelector('input[name="csrf_token"]');
    var csrfToken = csrfEl ? csrfEl.value : '';

    try {
      var payload = {
        username_email: account,
        descriptor: JSON.stringify(descriptorArray),
        csrf_token: csrfToken
      };

      var baseUri = window.location.pathname.replace(/\/auth\/.*$/, '');
      var response = await fetch(baseUri + '/api/face_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      var result = await response.json();

      if (result.success) {
        // Success: legitimate account owner confirmed
        if (targetBox) {
          targetBox.className = 'border border-3 border-success rounded-4';
          targetBox.style.boxShadow = '0 0 30px rgba(25, 135, 84, 1)';
        }
        var greet = result.user_name ? ('Welcome, ' + result.user_name + '!') : 'Account Owner Verified!';
        showMessage('<i class="fa-solid fa-circle-check text-success me-2"></i>' + greet + ' Redirecting…', 'success');
        stopCamera();
        setTimeout(function () { window.location.href = result.redirect; }, 900);
      } else {
        // Access Denied: face mismatch or unauthorized person
        if (targetBox) {
          targetBox.className = 'border border-3 border-danger rounded-4';
          targetBox.style.boxShadow = '0 0 35px rgba(220, 53, 69, 1)';
        }

        // Stop auto-scanning so an unauthorized face cannot trigger continuous scans
        if (scanInterval) {
          clearInterval(scanInterval);
          scanInterval = null;
        }

        showMessage(
          '<div class="text-center">' +
          '<i class="fa-solid fa-ban text-danger me-2 fs-5"></i><strong class="text-danger">Access is Denied (cannot be scanned)</strong>' +
          '<div class="small text-danger mt-1">' + escapeHtml(result.message || 'Your face does not match this account. You cannot log in.') + '</div>' +
          '<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-danger px-3 rounded-pill" id="retryScanBtn"><i class="fa-solid fa-rotate-right me-1"></i> Try Again</button></div>' +
          '</div>',
          'danger'
        );

        var retryBtn = document.getElementById('retryScanBtn');
        if (retryBtn) {
          retryBtn.addEventListener('click', function () {
            isSubmitting = false;
            resetBoxVisual();
            startAutoScan();
          });
        }

        setTimeout(function () {
          isSubmitting = false;
        }, 1500);
      }
    } catch (err) {
      console.error('Face verification error:', err);
      showMessage('Network error during verification. Please try again.', 'danger');
      isSubmitting = false;
      resetBoxVisual();
    }
  }

  // Continuous auto-scan loop - ONLY runs when an account is specified
  function startAutoScan() {
    if (scanInterval || isSubmitting) return;

    var account = getActiveAccount();
    if (!account) {
      showMessage('<i class="fa-solid fa-user-lock text-primary me-2"></i>Enter your Username or Student ID above. The camera will only scan the owner of that account.', 'warning');
      if (modalUserInput) modalUserInput.focus();
      return;
    }

    showMessage('<i class="fa-solid fa-crosshairs fa-beat-fade me-2 text-primary"></i>Scanning… Verifying that you are the registered owner of <strong>' + escapeHtml(account) + '</strong>.', 'info');

    scanInterval = setInterval(async function () {
      if (isSubmitting || !video || video.paused || video.ended) return;

      var currentAcc = getActiveAccount();
      if (!currentAcc) {
        clearInterval(scanInterval);
        scanInterval = null;
        showMessage('<i class="fa-solid fa-user-lock text-primary me-2"></i>Please enter your Username or Student ID above.', 'warning');
        return;
      }

      try {
        var detection = await faceapi.detectSingleFace(video, fastDetectorOptions())
          .withFaceLandmarks()
          .withFaceDescriptor();

        if (detection && detection.descriptor) {
          clearInterval(scanInterval);
          scanInterval = null;

          // Dual-frame biometric stability verification
          await new Promise(function(r) { setTimeout(r, 100); });
          var detection2 = await faceapi.detectSingleFace(video, fastDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptor();

          var finalDescriptor;
          if (detection2 && detection2.descriptor) {
            finalDescriptor = [];
            for (var i = 0; i < detection.descriptor.length; i++) {
              finalDescriptor.push((detection.descriptor[i] + detection2.descriptor[i]) / 2.0);
            }
          } else {
            finalDescriptor = Array.from(detection.descriptor);
          }

          await verifyDescriptor(finalDescriptor);
        }
      } catch (e) {
        // Continue scanning
      }
    }, 120);
  }

  // Synchronize inputs between modal and page
  if (modalUserInput && pageUserInput) {
    modalUserInput.addEventListener('input', function () {
      pageUserInput.value = modalUserInput.value;
      if (!isSubmitting && modalUserInput.value.trim().length >= 2) {
        if (!scanInterval) startAutoScan();
      }
    });

    pageUserInput.addEventListener('input', function () {
      modalUserInput.value = pageUserInput.value;
    });
  }

  // Modal open lifecycle
  modal.addEventListener('shown.bs.modal', async function () {
    isSubmitting = false;

    // Sync input value
    if (modalUserInput && pageUserInput) {
      modalUserInput.value = pageUserInput.value.trim();
    }

    showMessage('<i class="fa-solid fa-spinner fa-spin me-2"></i>Starting camera…', 'info');

    var cameraReady = await startCamera();
    if (!cameraReady) return;

    var ready = await ensureModels();
    if (!ready) {
      showMessage('Could not load AI models. Check your internet connection.', 'danger');
      return;
    }

    var account = getActiveAccount();
    if (!account) {
      if (modalUserInput) modalUserInput.focus();
      showMessage('<i class="fa-solid fa-user-lock text-primary me-2"></i>Please enter your Username or Student ID above. Only the recorded owner of that account can be scanned.', 'warning');
    } else {
      startAutoScan();
    }
  });

  modal.addEventListener('hidden.bs.modal', function () {
    stopCamera();
    messageEl.innerHTML = '';
  });

  // Manual Capture button
  if (captureBtn) {
    captureBtn.addEventListener('click', async function () {
      if (isSubmitting) return;

      var account = getActiveAccount();
      if (!account) {
        showMessage('<i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Please enter your Username or Student ID first.', 'warning');
        if (modalUserInput) modalUserInput.focus();
        return;
      }

      if (!modelsLoaded) {
        showMessage('Biometric models are still initializing…', 'warning');
        return;
      }

      try {
        var detection = await faceapi.detectSingleFace(video, fastDetectorOptions())
          .withFaceLandmarks()
          .withFaceDescriptor();

        if (detection && detection.descriptor) {
          clearInterval(scanInterval);
          scanInterval = null;

          await new Promise(function(r) { setTimeout(r, 100); });
          var detection2 = await faceapi.detectSingleFace(video, fastDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptor();

          var finalDescriptor;
          if (detection2 && detection2.descriptor) {
            finalDescriptor = [];
            for (var i = 0; i < detection.descriptor.length; i++) {
              finalDescriptor.push((detection.descriptor[i] + detection2.descriptor[i]) / 2.0);
            }
          } else {
            finalDescriptor = Array.from(detection.descriptor);
          }

          await verifyDescriptor(finalDescriptor);
        } else {
          showMessage('No face detected clearly in frame. Adjust position.', 'warning');
        }
      } catch (err) {
        showMessage('Capture error. Try again.', 'danger');
      }
    });
  }
})();
