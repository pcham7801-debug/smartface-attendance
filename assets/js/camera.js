/**
 * Camera Helper Module for SmartFace Attendance Monitoring System
 */

class CameraHelper {
    constructor(videoElement, canvasElement) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.stream = null;
        this.isStreaming = false;
    }

    async startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Webcam access is not supported by your browser or environment.");
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: "user"
                },
                audio: false
            });

            this.video.srcObject = this.stream;
            
            return new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    this.isStreaming = true;
                    if (this.canvas) {
                        this.canvas.width = this.video.videoWidth;
                        this.canvas.height = this.video.videoHeight;
                    }
                    resolve(true);
                };
            });
        } catch (err) {
            console.error("Camera Error:", err);
            throw err;
        }
    }

    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.video.srcObject = null;
            this.isStreaming = false;
        }
    }

    captureFrame() {
        if (!this.isStreaming) return null;
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = this.video.videoWidth;
        tempCanvas.height = this.video.videoHeight;
        const ctx = tempCanvas.getContext('2d');
        ctx.drawImage(this.video, 0, 0, tempCanvas.width, tempCanvas.height);
        return tempCanvas.toDataURL('image/jpeg', 0.9);
    }
}
