/**
 * User Behavior Tracking Script
 * Automatically collects user interaction data for analytics
 */

class UserBehaviorTracker {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.pageStartTime = Date.now();
        this.lastActivityTime = Date.now();
        this.scrollDepth = 0;
        this.maxScrollDepth = 0;
        this.clickCount = 0;
        this.keystrokes = 0;
        this.errors = [];
        this.performanceData = {};
        this.taskTracking = {};
        
        this.init();
    }
    
    init() {
        this.trackPageLoad();
        this.trackUserInteractions();
        this.trackPerformance();
        this.trackErrors();
        this.trackScrollBehavior();
        this.trackTaskCompletion();
        this.setupBeforeUnload();
        
        // Send data periodically
        setInterval(() => this.sendBehaviorData(), 30000); // Every 30 seconds
    }
    
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    trackPageLoad() {
        // Collect page load performance
        if (window.performance && window.performance.timing) {
            const timing = window.performance.timing;
            this.performanceData = {
                loadTime: timing.loadEventEnd - timing.navigationStart,
                domReadyTime: timing.domContentLoadedEventEnd - timing.navigationStart,
                firstPaintTime: this.getFirstPaintTime(),
                pageUrl: window.location.pathname,
                referrer: document.referrer,
                userAgent: navigator.userAgent,
                screenResolution: screen.width + 'x' + screen.height,
                deviceType: this.getDeviceType(),
                browser: this.getBrowser(),
                os: this.getOS()
            };
        }
        
        // Track page visit
        this.sendPageVisit();
    }
    
    trackUserInteractions() {
        // Track clicks
        document.addEventListener('click', (e) => {
            this.clickCount++;
            this.lastActivityTime = Date.now();
            
            // Track specific element interactions
            const element = e.target;
            const elementInfo = {
                tagName: element.tagName,
                className: element.className,
                id: element.id,
                text: element.textContent?.substring(0, 100),
                href: element.href || null
            };
            
            this.sendInteractionData('click', elementInfo);
        });
        
        // Track form interactions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const formData = {
                action: form.action,
                method: form.method,
                formId: form.id,
                fieldCount: form.elements.length
            };
            
            this.sendInteractionData('form_submit', formData);
        });
        
        // Track input focus (task start indicators)
        document.addEventListener('focusin', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                this.trackTaskStart(e.target);
            }
        });
        
        // Track keystrokes
        document.addEventListener('keydown', () => {
            this.keystrokes++;
            this.lastActivityTime = Date.now();
        });
    }
    
    trackScrollBehavior() {
        let ticking = false;
        
        const updateScrollDepth = () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            this.scrollDepth = Math.round((scrollTop / docHeight) * 100);
            this.maxScrollDepth = Math.max(this.maxScrollDepth, this.scrollDepth);
            ticking = false;
        };
        
        window.addEventListener('scroll', () => {
            this.lastActivityTime = Date.now();
            if (!ticking) {
                requestAnimationFrame(updateScrollDepth);
                ticking = true;
            }
        });
    }
    
    trackTaskCompletion() {
        // Track common business tasks
        this.trackCampaignCreation();
        this.trackStoreItemAddition();
        this.trackQRGeneration();
        this.trackNayaxSetup();
    }
    
    trackCampaignCreation() {
        const campaignForms = document.querySelectorAll('form[action*="campaign"], form[id*="campaign"]');
        campaignForms.forEach(form => {
            const taskId = 'campaign_' + Date.now();
            this.taskTracking[taskId] = {
                type: 'create_campaign',
                startTime: Date.now(),
                steps: ['form_start', 'details_filled', 'items_selected', 'submitted'],
                currentStep: 0
            };
            
            form.addEventListener('submit', () => {
                this.completeTask(taskId);
            });
        });
    }
    
    trackStoreItemAddition() {
        const itemForms = document.querySelectorAll('form[action*="add-item"], form[id*="item"]');
        itemForms.forEach(form => {
            const taskId = 'add_item_' + Date.now();
            this.taskTracking[taskId] = {
                type: 'add_store_item',
                startTime: Date.now(),
                steps: ['form_start', 'details_filled', 'image_uploaded', 'submitted'],
                currentStep: 0
            };
            
            form.addEventListener('submit', () => {
                this.completeTask(taskId);
            });
        });
    }
    
    trackQRGeneration() {
        const qrButtons = document.querySelectorAll('button[onclick*="generateQR"], .generate-qr');
        qrButtons.forEach(button => {
            button.addEventListener('click', () => {
                const taskId = 'generate_qr_' + Date.now();
                this.taskTracking[taskId] = {
                    type: 'generate_qr',
                    startTime: Date.now(),
                    steps: ['initiated', 'completed'],
                    currentStep: 0
                };
                
                // Check for QR code appearance
                setTimeout(() => {
                    const qrImages = document.querySelectorAll('img[src*="qr"], .qr-code');
                    if (qrImages.length > 0) {
                        this.completeTask(taskId);
                    }
                }, 2000);
            });
        });
    }
    
    trackNayaxSetup() {
        const nayaxElements = document.querySelectorAll('[href*="nayax"], [onclick*="nayax"]');
        nayaxElements.forEach(element => {
            element.addEventListener('click', () => {
                const taskId = 'setup_nayax_' + Date.now();
                this.taskTracking[taskId] = {
                    type: 'setup_nayax',
                    startTime: Date.now(),
                    steps: ['initiated', 'configuration', 'testing', 'completed'],
                    currentStep: 0
                };
            });
        });
    }
    
    trackTaskStart(element) {
        // Identify task type based on form context
        const form = element.closest('form');
        if (!form) return;
        
        let taskType = 'unknown';
        if (form.action.includes('campaign') || form.id.includes('campaign')) {
            taskType = 'create_campaign';
        } else if (form.action.includes('item') || form.id.includes('item')) {
            taskType = 'add_store_item';
        } else if (form.action.includes('nayax') || form.id.includes('nayax')) {
            taskType = 'setup_nayax';
        }
        
        if (taskType !== 'unknown') {
            const taskId = taskType + '_' + Date.now();
            this.sendTaskStart(taskId, taskType);
        }
    }
    
    completeTask(taskId) {
        if (this.taskTracking[taskId]) {
            const task = this.taskTracking[taskId];
            const completionTime = Math.round((Date.now() - task.startTime) / 1000);
            this.sendTaskCompletion(taskId, task.type, completionTime);
            delete this.taskTracking[taskId];
        }
    }
    
    trackPerformance() {
        // Track Core Web Vitals
        if ('web-vital' in window) {
            // This would require the web-vitals library
            // For now, we'll track basic performance metrics
        }
        
        // Track slow interactions
        const observer = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.duration > 100) { // Slow interaction threshold
                    this.sendPerformanceData({
                        type: 'slow_interaction',
                        duration: entry.duration,
                        startTime: entry.startTime,
                        name: entry.name
                    });
                }
            }
        });
        
        try {
            observer.observe({ entryTypes: ['measure', 'navigation'] });
        } catch (e) {
            // Performance Observer not supported
        }
    }
    
    trackErrors() {
        // Track JavaScript errors
        window.addEventListener('error', (e) => {
            this.errors.push({
                type: 'js_error',
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                stack: e.error?.stack,
                timestamp: Date.now()
            });
            
            this.sendErrorData({
                type: 'js_error',
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                userAction: 'page_interaction'
            });
        });
        
        // Track unhandled promise rejections
        window.addEventListener('unhandledrejection', (e) => {
            this.sendErrorData({
                type: 'promise_rejection',
                message: e.reason?.toString() || 'Unhandled promise rejection',
                userAction: 'async_operation'
            });
        });
    }
    
    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            this.sendPageExit();
        });
        
        // Track page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.sendBehaviorData();
            }
        });
    }
    
    // Data sending methods
    sendPageVisit() {
        const data = {
            action: 'page_visit',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            pageTitle: document.title,
            referrer: document.referrer,
            userAgent: navigator.userAgent,
            screenResolution: screen.width + 'x' + screen.height,
            deviceType: this.getDeviceType(),
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendPageExit() {
        const timeSpent = Math.round((Date.now() - this.pageStartTime) / 1000);
        const isBounce = this.clickCount === 0 && timeSpent < 10;
        
        const data = {
            action: 'page_exit',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            timeSpent: timeSpent,
            bounce: isBounce ? 1 : 0,
            clickCount: this.clickCount,
            keystrokes: this.keystrokes,
            maxScrollDepth: this.maxScrollDepth,
            timestamp: Date.now()
        };
        
        // Use sendBeacon for reliable delivery on page unload
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/track-behavior.php', JSON.stringify(data));
        } else {
            this.sendData(data);
        }
    }
    
    sendBehaviorData() {
        const data = {
            action: 'behavior_update',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            clickCount: this.clickCount,
            keystrokes: this.keystrokes,
            scrollDepth: this.maxScrollDepth,
            timeSpent: Math.round((Date.now() - this.pageStartTime) / 1000),
            lastActivity: this.lastActivityTime,
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendInteractionData(type, elementInfo) {
        const data = {
            action: 'interaction',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            interactionType: type,
            elementInfo: elementInfo,
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendTaskStart(taskId, taskType) {
        const data = {
            action: 'task_start',
            sessionId: this.sessionId,
            taskId: taskId,
            taskType: taskType,
            pageUrl: window.location.pathname,
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendTaskCompletion(taskId, taskType, completionTime) {
        const data = {
            action: 'task_complete',
            sessionId: this.sessionId,
            taskId: taskId,
            taskType: taskType,
            completionTime: completionTime,
            pageUrl: window.location.pathname,
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendPerformanceData(performanceInfo) {
        const data = {
            action: 'performance',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            performanceData: {
                ...this.performanceData,
                ...performanceInfo
            },
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendErrorData(errorInfo) {
        const data = {
            action: 'error',
            sessionId: this.sessionId,
            pageUrl: window.location.pathname,
            errorData: errorInfo,
            timestamp: Date.now()
        };
        
        this.sendData(data);
    }
    
    sendData(data) {
        // Send data to tracking endpoint
        fetch('/api/track-behavior.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        }).catch(error => {
            console.warn('Tracking data failed to send:', error);
        });
    }
    
    // Utility methods
    getDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        if (/tablet|ipad|playbook|silk/.test(userAgent)) {
            return 'tablet';
        } else if (/mobile|iphone|ipod|android|blackberry|opera|mini|windows\sce|palm|smartphone|iemobile/.test(userAgent)) {
            return 'mobile';
        } else {
            return 'desktop';
        }
    }
    
    getBrowser() {
        const userAgent = navigator.userAgent;
        if (userAgent.includes('Chrome')) return 'Chrome';
        if (userAgent.includes('Firefox')) return 'Firefox';
        if (userAgent.includes('Safari')) return 'Safari';
        if (userAgent.includes('Edge')) return 'Edge';
        if (userAgent.includes('Opera')) return 'Opera';
        return 'Unknown';
    }
    
    getOS() {
        const userAgent = navigator.userAgent;
        if (userAgent.includes('Windows')) return 'Windows';
        if (userAgent.includes('Mac')) return 'macOS';
        if (userAgent.includes('Linux')) return 'Linux';
        if (userAgent.includes('Android')) return 'Android';
        if (userAgent.includes('iOS')) return 'iOS';
        return 'Unknown';
    }
    
    getFirstPaintTime() {
        if (window.performance && window.performance.getEntriesByType) {
            const paintEntries = window.performance.getEntriesByType('paint');
            const firstPaint = paintEntries.find(entry => entry.name === 'first-paint');
            return firstPaint ? firstPaint.startTime : null;
        }
        return null;
    }
}

// Initialize tracking when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.userBehaviorTracker = new UserBehaviorTracker();
    });
} else {
    window.userBehaviorTracker = new UserBehaviorTracker();
}

// Export for manual usage
window.UserBehaviorTracker = UserBehaviorTracker; 