/**
 * Face Recognition Engine using face-api.js for SmartFace Attendance System
 */

class FaceRecognitionEngine {
    constructor(videoElement, canvasElement, statusBadgeElement) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.statusBadge = statusBadgeElement;
        this.modelsLoaded = false;
        this.detectionInterval = null;
        this.modelPath = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
    }

    async loadModels() {
        this.updateStatus('Loading Face Recognition AI Models...', 'bg-warning text-dark');
        try {
            if (typeof faceapi === 'undefined') {
                throw new Error("face-api.js library is not loaded.");
            }
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(this.modelPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(this.modelPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(this.modelPath)
            ]);
            this.modelsLoaded = true;
            this.updateStatus('Camera & Models Ready', 'bg-success text-white');
            return true;
        } catch (err) {
            console.error("Model Loading Error:", err);
            this.updateStatus('Error Loading Models (Check Connection)', 'bg-danger text-white');
            throw err;
        }
    }

    updateStatus(message, bgClass = 'bg-info text-white') {
        if (this.statusBadge) {
            this.statusBadge.className = `face-status-badge ${bgClass}`;
            this.statusBadge.innerHTML = message;
        }
    }

    async captureFaceDescriptor() {
        if (!this.modelsLoaded) {
            await this.loadModels();
        }

        const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        
        this.updateStatus('Looking for face...', 'bg-info text-white');
        
        let attempts = 0;
        const maxAttempts = 30; // 30 frames ~ 3-5 seconds

        return new Promise((resolve, reject) => {
            const timer = setInterval(async () => {
                attempts++;
                if (!this.video || this.video.paused || this.video.ended) {
                    clearInterval(timer);
                    reject(new Error("Video stream stopped."));
                    return;
                }

                const detection = await faceapi.detectSingleFace(this.video, options)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    // Draw bounding box
                    if (this.canvas) {
                        const dims = faceapi.matchDimensions(this.canvas, this.video, true);
                        const resizedDetections = faceapi.resizeResults(detection, dims);
                        const ctx = this.canvas.getContext('2d');
                        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                        faceapi.draw.drawDetections(this.canvas, resizedDetections);
                        faceapi.draw.drawFaceLandmarks(this.canvas, resizedDetections);
                    }

                    this.updateStatus('Face Detected!', 'bg-success text-white');
                    clearInterval(timer);
                    
                    // Convert descriptor Float32Array to standard JS Array
                    const descriptorArray = Array.from(detection.descriptor);
                    resolve(descriptorArray);
                } else if (attempts >= maxAttempts) {
                    clearInterval(timer);
                    this.updateStatus('No face detected. Adjust lighting and position.', 'bg-danger text-white');
                    reject(new Error("No face detected. Please ensure your face is clearly visible."));
                }
            }, 200);
        });
    }

    async startLiveDetection(onFaceDetectedCallback) {
        if (!this.modelsLoaded) {
            await this.loadModels();
        }

        const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        
        this.detectionInterval = setInterval(async () => {
            if (!this.video || this.video.paused || this.video.ended) return;

            const detection = await faceapi.detectSingleFace(this.video, options)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (this.canvas) {
                const dims = faceapi.matchDimensions(this.canvas, this.video, true);
                const ctx = this.canvas.getContext('2d');
                ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                if (detection) {
                    const resizedDetections = faceapi.resizeResults(detection, dims);
                    faceapi.draw.drawDetections(this.canvas, resizedDetections);
                    this.updateStatus('Face Detected', 'bg-success text-white');
                    
                    if (onFaceDetectedCallback && typeof onFaceDetectedCallback === 'function') {
                        onFaceDetectedCallback(Array.from(detection.descriptor));
                    }
                } else {
                    this.updateStatus('Position your face in camera', 'bg-warning text-dark');
                }
            }
        }, 250);
    }

    stopLiveDetection() {
        if (this.detectionInterval) {
            clearInterval(this.detectionInterval);
            this.detectionInterval = null;
        }
        if (this.canvas) {
            const ctx = this.canvas.getContext('2d');
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
    }
}
