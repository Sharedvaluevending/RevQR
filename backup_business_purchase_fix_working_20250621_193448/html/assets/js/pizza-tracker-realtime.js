/**
 * Pizza Tracker Real-time Client
 * Handles WebSocket connections, offline mode, and mobile optimizations
 */

class PizzaTrackerRealtime {
    constructor(trackerId, options = {}) {
        this.trackerId = trackerId;
        this.options = {
            wsUrl: options.wsUrl || `ws://${window.location.hostname}:8080`,
            reconnectInterval: options.reconnectInterval || 5000,
            maxReconnectAttempts: options.maxReconnectAttempts || 10,
            offlineSupport: options.offlineSupport !== false,
            autoRefresh: options.autoRefresh !== false,
            refreshInterval: options.refreshInterval || 30000,
            enableNotifications: options.enableNotifications !== false,
            ...options
        };
        
        this.ws = null;
        this.reconnectAttempts = 0;
        this.isConnected = false;
        this.isOnline = navigator.onLine;
        this.listeners = {};
        this.cachedData = null;
        this.lastUpdate = null;
        
        this.init();
    }
    
    init() {
        this.setupOfflineSupport();
        this.setupNotifications();
        this.connectWebSocket();
        this.setupAutoRefresh();
        this.loadCachedData();
        
        // Setup event listeners
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        window.addEventListener('beforeunload', () => this.disconnect());
        
        // Mobile specific optimizations
        if (this.isMobile()) {
            this.setupMobileOptimizations();
        }
    }
    
    connectWebSocket() {
        if (!this.isOnline || this.ws) {
            return;
        }
        
        try {
            this.ws = new WebSocket(this.options.wsUrl);
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.subscribeToTracker();
                this.emit('connected');
            };
            
            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.isConnected = false;
                this.ws = null;
                this.scheduleReconnect();
                this.emit('disconnected');
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.emit('error', error);
            };
        } catch (error) {
            console.error('Failed to connect WebSocket:', error);
            this.scheduleReconnect();
        }
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            console.log('Max reconnection attempts reached');
            this.emit('max_reconnect_attempts');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.options.reconnectInterval * Math.pow(2, this.reconnectAttempts - 1);
        
        setTimeout(() => {
            if (this.isOnline && !this.isConnected) {
                console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.options.maxReconnectAttempts})`);
                this.connectWebSocket();
            }
        }, delay);
    }
    
    subscribeToTracker() {
        if (this.isConnected && this.ws) {
            this.sendWebSocketMessage({
                action: 'subscribe_tracker',
                tracker_id: this.trackerId
            });
        }
    }
    
    sendWebSocketMessage(message) {
        if (this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
        }
    }
    
    handleWebSocketMessage(data) {
        switch (data.action) {
            case 'tracker_update':
                this.handleTrackerUpdate(data.data);
                break;
            case 'pong':
                // Handle ping response
                break;
            default:
                console.log('Unknown WebSocket message:', data);
        }
    }
    
    handleTrackerUpdate(trackerData) {
        this.cachedData = trackerData;
        this.lastUpdate = new Date();
        this.saveToCache();
        this.emit('tracker_update', trackerData);
        
        // Check for milestone achievements
        this.checkMilestones(trackerData);
    }
    
    checkMilestones(trackerData) {
        const milestones = [25, 50, 75, 90, 100];
        const currentProgress = trackerData.progress_percent;
        const previousProgress = this.cachedData?.progress_percent || 0;
        
        milestones.forEach(milestone => {
            if (previousProgress < milestone && currentProgress >= milestone) {
                this.showMilestoneNotification(milestone, trackerData);
                this.emit('milestone_reached', { milestone, tracker: trackerData });
            }
        });
    }
    
    showMilestoneNotification(milestone, trackerData) {
        if (!this.options.enableNotifications || !('Notification' in window)) {
            return;
        }
        
        const messages = {
            25: "🍕 25% Complete! Quarter way to pizza time!",
            50: "🍕 Halfway There! Pizza is getting closer!",
            75: "🍕 75% Complete! Almost time for pizza!",
            90: "🍕 So Close! 90% complete!",
            100: "🎉 PIZZA TIME! Goal achieved!"
        };
        
        const message = messages[milestone] || "🍕 Milestone reached!";
        
        new Notification(message, {
            body: `${trackerData.name}: $${trackerData.current_revenue} / $${trackerData.revenue_goal}`,
            icon: '/assets/img/pizza-icon.png',
            badge: '/assets/img/pizza-badge.png',
            tag: `pizza-tracker-${this.trackerId}`,
            requireInteraction: milestone === 100
        });
    }
    
    setupNotifications() {
        if (!this.options.enableNotifications || !('Notification' in window)) {
            return;
        }
        
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }
    }
    
    setupOfflineSupport() {
        if (!this.options.offlineSupport) {
            return;
        }
        
        // Register service worker for offline support
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw-pizza-tracker.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        }
    }
    
    setupAutoRefresh() {
        if (!this.options.autoRefresh) {
            return;
        }
        
        setInterval(() => {
            if (!this.isConnected && this.isOnline) {
                this.fetchTrackerData();
            }
        }, this.options.refreshInterval);
    }
    
    setupMobileOptimizations() {
        // Optimize for mobile devices
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // App went to background
                this.handleAppBackground();
            } else {
                // App came to foreground
                this.handleAppForeground();
            }
        });
        
        // Handle mobile network changes
        if ('connection' in navigator) {
            navigator.connection.addEventListener('change', () => {
                this.handleNetworkChange();
            });
        }
        
        // Optimize battery usage
        this.optimizeBatteryUsage();
    }
    
    handleAppBackground() {
        // Reduce update frequency when app is in background
        if (this.isConnected) {
            this.sendWebSocketMessage({
                action: 'background_mode',
                tracker_id: this.trackerId
            });
        }
    }
    
    handleAppForeground() {
        // Resume normal operation when app comes to foreground
        if (this.isConnected) {
            this.sendWebSocketMessage({
                action: 'foreground_mode',
                tracker_id: this.trackerId
            });
        } else if (this.isOnline) {
            this.connectWebSocket();
        }
        
        // Fetch latest data
        this.fetchTrackerData();
    }
    
    handleNetworkChange() {
        const connection = navigator.connection;
        const effectiveType = connection.effectiveType;
        
        // Adjust update frequency based on connection speed
        if (effectiveType === 'slow-2g' || effectiveType === '2g') {
            this.options.refreshInterval = 60000; // 1 minute
        } else if (effectiveType === '3g') {
            this.options.refreshInterval = 30000; // 30 seconds
        } else {
            this.options.refreshInterval = 15000; // 15 seconds
        }
    }
    
    optimizeBatteryUsage() {
        if ('getBattery' in navigator) {
            navigator.getBattery().then(battery => {
                if (battery.level < 0.2) { // Battery below 20%
                    this.options.refreshInterval *= 2; // Reduce frequency
                    console.log('Low battery detected, reducing update frequency');
                }
            });
        }
    }
    
    handleOnline() {
        this.isOnline = true;
        console.log('Connection restored');
        this.connectWebSocket();
        this.fetchTrackerData();
        this.emit('online');
    }
    
    handleOffline() {
        this.isOnline = false;
        console.log('Connection lost - switching to offline mode');
        this.disconnect();
        this.loadCachedData();
        this.emit('offline');
    }
    
    async fetchTrackerData() {
        try {
            const response = await fetch(`/api/pizza-tracker/v1/trackers/${this.trackerId}`);
            if (response.ok) {
                const data = await response.json();
                this.handleTrackerUpdate(data.data.tracker);
            }
        } catch (error) {
            console.error('Failed to fetch tracker data:', error);
            this.emit('fetch_error', error);
        }
    }
    
    saveToCache() {
        if (!this.options.offlineSupport) {
            return;
        }
        
        try {
            const cacheData = {
                tracker: this.cachedData,
                timestamp: this.lastUpdate?.getTime(),
                version: '1.0'
            };
            
            localStorage.setItem(`pizza_tracker_${this.trackerId}`, JSON.stringify(cacheData));
        } catch (error) {
            console.error('Failed to save to cache:', error);
        }
    }
    
    loadCachedData() {
        if (!this.options.offlineSupport) {
            return;
        }
        
        try {
            const cached = localStorage.getItem(`pizza_tracker_${this.trackerId}`);
            if (cached) {
                const cacheData = JSON.parse(cached);
                this.cachedData = cacheData.tracker;
                this.lastUpdate = new Date(cacheData.timestamp);
                this.emit('cached_data_loaded', this.cachedData);
            }
        } catch (error) {
            console.error('Failed to load cached data:', error);
        }
    }
    
    // Event system
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }
    
    emit(event, data = null) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in event listener for ${event}:`, error);
                }
            });
        }
    }
    
    // Utility methods
    isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    getConnectionStatus() {
        return {
            websocket: this.isConnected,
            online: this.isOnline,
            lastUpdate: this.lastUpdate,
            reconnectAttempts: this.reconnectAttempts
        };
    }
    
    getCurrentData() {
        return this.cachedData;
    }
    
    // Manual refresh
    refresh() {
        if (this.isOnline) {
            this.fetchTrackerData();
        }
    }
    
    // Disconnect
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.isConnected = false;
    }
    
    // Performance monitoring
    getPerformanceMetrics() {
        return {
            trackerId: this.trackerId,
            isConnected: this.isConnected,
            isOnline: this.isOnline,
            reconnectAttempts: this.reconnectAttempts,
            lastUpdate: this.lastUpdate,
            cacheSize: this.cachedData ? JSON.stringify(this.cachedData).length : 0,
            refreshInterval: this.options.refreshInterval
        };
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PizzaTrackerRealtime;
} else {
    window.PizzaTrackerRealtime = PizzaTrackerRealtime;
}

// Progressive Web App helpers
class PizzaTrackerPWA {
    static init() {
        // Add to home screen prompt
        this.setupInstallPrompt();
        
        // Update available notification
        this.setupUpdateNotification();
    }
    
    static setupInstallPrompt() {
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show custom install button
            const installBtn = document.getElementById('install-app-btn');
            if (installBtn) {
                installBtn.style.display = 'block';
                installBtn.addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        console.log('Install prompt result:', choiceResult.outcome);
                        deferredPrompt = null;
                        installBtn.style.display = 'none';
                    });
                });
            }
        });
    }
    
    static setupUpdateNotification() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                // Show update notification
                this.showUpdateNotification();
            });
        }
    }
    
    static showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.innerHTML = `
            <div class="alert alert-info alert-dismissible">
                <strong>App Updated!</strong> A new version is available.
                <button class="btn btn-sm btn-primary ms-2" onclick="location.reload()">Refresh</button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 10000);
    }
}

// Initialize PWA features
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PizzaTrackerPWA.init());
} else {
    PizzaTrackerPWA.init();
} 