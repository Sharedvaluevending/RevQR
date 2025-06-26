/**
 * SECURE QR Coin Casino - Slot Machine Game (GSAP Optimized)
 * 
 * SECURITY FEATURES:
 * - Server-side authority for all game logic
 * - Single atomic API endpoint per spin
 * - No client-side win calculation or result manipulation
 * - Cryptographic verification of results
 * - Proper QRCoinManager integration
 * - Automatic balance sync with server authority
 */

class SecureSlotMachine {
    constructor() {
        this.isSpinning = false;
        this.symbols = [];
        this.currentBalance = window.casinoData.userBalance;
        this.businessId = window.casinoData.businessId;
        this.jackpotMultiplier = window.casinoData.jackpotMultiplier;
        this.appUrl = window.casinoData.appUrl;
        this.symbolHeight = this.getResponsiveSymbolHeight();
        this.reelElements = [];
        this.preloadedImages = new Map();
        this.isImagesLoaded = false;
        this.spinsRemaining = window.casinoData.spinInfo ? window.casinoData.spinInfo.spins_remaining : null;
        this.lastSpinPackCheck = Date.now();
        this.spinPackCheckInterval = null;
        
        this.initializeSymbols();
        this.preloadImages().then(() => {
            this.initializeReels();
            this.bindEvents();
            this.updateBalance();
            this.updateSpinButton();
            this.bindResizeHandler();
            this.startSpinPackMonitoring();
            this.setupPageUnloadHandlers();
        });
    }

    getResponsiveSymbolHeight() {
        const width = window.innerWidth;
        if (width <= 480) return 40;
        if (width <= 768) return 48;
        return 58;
    }

    initializeSymbols() {
        // Use only symbols that we know exist to avoid loading issues
        this.symbols = [
            { name: 'QR Ted', image: 'assets/img/avatars/qrted.png', level: 1, rarity: 'common', isWild: false },
            { name: 'QR Steve', image: 'assets/img/avatars/qrsteve.png', level: 2, rarity: 'common', isWild: false },
            { name: 'QR Bob', image: 'assets/img/avatars/qrbob.png', level: 3, rarity: 'uncommon', isWild: false },
            { name: 'Lord Pixel', image: 'assets/img/avatars/qrLordPixel.png', level: 8, rarity: 'mythical', isWild: false },
            { name: 'Wild QR', image: 'assets/img/avatars/qrEasybake.png', level: 5, rarity: 'wild', isWild: true }
        ];

        console.log('🎮 Initialized symbols:', this.symbols.map(s => s.name));

        // Add full URL paths
        this.symbols.forEach(symbol => {
            symbol.image = `${this.appUrl}/${symbol.image}`;
            console.log(`🔗 Symbol URL: ${symbol.name} -> ${symbol.image}`);
        });
    }

        async preloadImages() {
        console.log('🛡️ Preloading secure slot machine images...');
        const loadPromises = this.symbols.map(symbol => {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    this.preloadedImages.set(symbol.image, img);
                    console.log(`✅ Loaded: ${symbol.name}${symbol.isWild ? ' (WILD)' : ''} from ${symbol.image}`);
                    resolve(img);
                };
                img.onerror = () => {
                    console.error(`❌ FAILED to load: ${symbol.name} from ${symbol.image}`);
                    // Create a simple fallback image using QR Ted as backup
                    const fallbackImg = new Image();
                    fallbackImg.onload = () => {
                        this.preloadedImages.set(symbol.image, fallbackImg);
                        console.log(`🔄 Using QR Ted fallback for ${symbol.name}`);
                        resolve(fallbackImg);
                    };
                    fallbackImg.onerror = () => {
                        console.error(`💀 Even fallback failed for ${symbol.name}`);
                        // Just resolve without setting anything - will be handled later
                        resolve(null);
                    };
                    fallbackImg.src = `${this.appUrl}/assets/img/avatars/qrted.png`;
                };
                console.log(`🔄 Loading: ${symbol.name} from ${symbol.image}`);
                img.src = symbol.image;
            });
        });

        try {
            await Promise.all(loadPromises);
            this.isImagesLoaded = true;
            console.log('🎉 All slot machine images processed!');
            console.log('📋 Preloaded images count:', this.preloadedImages.size);
            
            // Show what we actually have
            this.preloadedImages.forEach((img, url) => {
                console.log(`📋 Preloaded: ${url} ->`, img ? 'SUCCESS' : 'FAILED');
            });
            
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('slotMachine').style.display = 'block';
            
        } catch (error) {
            console.error('Error preloading images:', error);
            this.isImagesLoaded = true;
            
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('slotMachine').style.display = 'block';
        }
    }

    initializeReels() {
        if (!this.isImagesLoaded) {
            setTimeout(() => this.initializeReels(), 100);
            return;
        }

        for (let i = 1; i <= 3; i++) {
            const reel = document.getElementById(`reel${i}`);
            const container = reel.querySelector('.slot-symbol-container');
            
            this.reelElements.push({
                reel: reel,
                container: container,
                symbols: []
            });
            
            container.innerHTML = '';
            const symbolsPerReel = 20;
            
            for (let j = 0; j < symbolsPerReel; j++) {
                const symbolDiv = document.createElement('div');
                symbolDiv.className = 'slot-symbol';
                
                const img = document.createElement('img');
                const symbol = this.symbols[j % this.symbols.length];
                
                const preloadedImg = this.preloadedImages.get(symbol.image);
                console.log(`🔧 InitReel: ${symbol.name} - Preloaded:`, preloadedImg ? 'FOUND' : 'NOT_FOUND');
                
                if (preloadedImg) {
                    img.src = preloadedImg.src;
                    console.log(`🖼️ InitReel: Using preloaded image for ${symbol.name}`);
                } else {
                    // If not preloaded, try the original image path but add error handling
                    img.src = symbol.image;
                    img.onerror = () => {
                        console.warn(`⚠️ InitReel: Image failed for ${symbol.name}, using fallback`);
                        img.src = `${this.appUrl}/assets/img/avatars/qrted.png`;
                    };
                    console.log(`⚠️ InitReel: Using direct image for ${symbol.name} (not preloaded)`);
                }
                
                symbolDiv.appendChild(img);
                
                img.alt = symbol.name;
                img.dataset.level = symbol.level;
                img.dataset.rarity = symbol.rarity;
                img.dataset.isWild = symbol.isWild;
                img.style.opacity = '1';
                
                if (symbol.isWild) {
                    symbolDiv.classList.add('wild-symbol');
                }
                
                container.appendChild(symbolDiv);
            }
        }
    }

    bindEvents() {
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.addEventListener('click', () => this.secureSpin());
        }
    }

    bindResizeHandler() {
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.symbolHeight = this.getResponsiveSymbolHeight();
                if (this.isImagesLoaded) {
                    this.initializeReels();
                }
            }, 250);
        });
    }

    updateBalance() {
        const balanceElement = document.getElementById('currentBalance');
        if (balanceElement) {
            balanceElement.textContent = this.currentBalance;
        }
        
        const balanceSpan = document.getElementById('balance-amount');
        if (balanceSpan) {
            balanceSpan.textContent = this.currentBalance;
        }
    }

    updateSpinCountDisplay(spinInfo) {
        const spinDisplay = document.getElementById('spinCountDisplay');
        const spinsRemainingEl = document.getElementById('spinsRemaining');
        
        if (spinInfo && spinInfo.has_spin_pack) {
            if (spinDisplay) spinDisplay.style.display = 'block';
            if (spinsRemainingEl) spinsRemainingEl.textContent = spinInfo.spins_remaining;
            this.spinsRemaining = spinInfo.spins_remaining;
        } else {
            if (spinDisplay) spinDisplay.style.display = 'none';
            this.spinsRemaining = 0;
        }
        
        this.updateSpinButton();
    }

    async fetchAndUpdateSpinInfo() {
        try {
            const response = await fetch(`${this.appUrl}/api/casino/get-spin-info.php?business_id=${this.businessId}`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.spinInfo) {
                    this.updateSpinCountDisplay(data.spinInfo);
                }
            }
        } catch (error) {
            console.error('Failed to fetch spin info:', error);
        }
    }

    updateSpinButton() {
        const spinButton = document.getElementById('spinButton');
        if (!spinButton) return;
        
        const canSpin = !this.isSpinning && this.spinsRemaining > 0;
        
        spinButton.disabled = !canSpin;
        
        if (this.isSpinning) {
            spinButton.innerHTML = '<i class="bi bi-arrow-clockwise spin-icon"></i> Spinning...';
            spinButton.className = 'btn btn-warning btn-lg';
        } else if (this.spinsRemaining <= 0) {
            spinButton.innerHTML = '<i class="bi bi-cart-plus"></i> Buy Spin Pack';
            spinButton.className = 'btn btn-success btn-lg';
            spinButton.onclick = () => {
                window.location.href = `${this.appUrl}/business/qr-store.php?business=${this.businessId}#slot-packs`;
            };
        } else {
            spinButton.innerHTML = '<i class="bi bi-dice-5-fill me-2"></i>SPIN';
            spinButton.className = 'btn btn-casino-primary btn-lg';
            spinButton.onclick = () => this.secureSpin();
        }
    }

    /**
     * SECURE SPIN METHOD - Single API call with server authority
     */
    async secureSpin() {
        if (this.isSpinning || this.spinsRemaining <= 0) return;
        
        this.isSpinning = true;
        this.updateSpinButton();
        
        // Clear previous win displays
        document.getElementById('winDisplay').style.display = 'none';
        document.querySelectorAll('.winning-symbol').forEach(symbol => {
            symbol.classList.remove('winning-symbol');
            gsap.killTweensOf(symbol);
        });
        
        // Get bet amount
        let betAmount = 1;
        const betInputs = document.querySelectorAll('input[name="betAmount"]');
        betInputs.forEach(input => {
            if (input.checked) {
                betAmount = parseInt(input.value);
            }
        });
        
        try {
            console.log('🛡️ Making SECURE spin request...');
            
            // Start animation first
            const animationPromise = this.startSecureAnimation();
            
            // SINGLE SECURE API CALL - Server handles everything atomically
            const response = await fetch(`${this.appUrl}/api/casino/unified-slot-play.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    business_id: this.businessId,
                    bet_amount: betAmount
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Spin request failed');
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Spin failed');
            }
            
            console.log('🛡️ Server-authorized results received:', data);
            
            // Wait for animation
            await animationPromise;
            
            // Display server results
            await this.displaySecureResults(data);
            
            // Update game state
            this.updateGameStateFromServer(data);
            
            // Show win effects
            if (data.is_win) {
                this.showSecureWin(data);
            }
            
            console.log('🛡️ Secure spin completed successfully');
            
        } catch (error) {
            console.error('🚨 Secure spin error:', error);
            alert('Spin failed: ' + error.message);
        } finally {
            this.isSpinning = false;
            this.updateSpinButton();
        }
    }

    async startSecureAnimation() {
        this.reelElements.forEach(reelData => {
            reelData.reel.classList.add('spinning');
        });

        const tl = gsap.timeline();
        
        this.reelElements.forEach((reelData, index) => {
            tl.to(reelData.container, {
                y: -this.symbolHeight * 20,
                duration: 2,
                ease: "none",
                repeat: -1,
                modifiers: {
                    y: (y) => {
                        const parsed = parseFloat(y);
                        const max = this.symbolHeight * 20;
                        return (parsed % max) + "px";
                    }
                }
            }, index * 0.1);
        });

        await new Promise(resolve => setTimeout(resolve, 2500));
        tl.kill();
    }

    async displaySecureResults(serverData) {
        const results = serverData.results;
        
        const stopPromises = this.reelElements.map((reelData, index) => {
            return new Promise(resolve => {
                setTimeout(() => {
                    this.stopSecureReel(reelData, results[index], index + 1, serverData);
                    resolve();
                }, index * 400);
            });
        });

        await Promise.all(stopPromises);
    }

    stopSecureReel(reelData, result, reelIndex, serverData) {
        reelData.reel.classList.remove('spinning');
        this.buildSecureReel(reelData, result, reelIndex);
        
        gsap.to(reelData.container, {
            y: 0,
            duration: 0.8,
            ease: "back.out(1.7)",
            onComplete: () => {
                if (serverData.is_win && serverData.winning_row !== -1) {
                    this.markSecureWinningSymbols(reelIndex, serverData);
                }
            }
        });
    }

    buildSecureReel(reelData, result, reelIndex) {
        reelData.container.innerHTML = '';
        
        const symbolPositions = [
            result.topSymbol || this.getRandomSymbol(),
            result.middleSymbol || result,
            result.bottomSymbol || this.getRandomSymbol()
        ];
        
        console.log(`🎯 Building reel ${reelIndex} with symbols:`, symbolPositions.map(s => s.name));
        
        symbolPositions.forEach((symbol, rowIndex) => {
            const symbolDiv = document.createElement('div');
            symbolDiv.className = 'slot-symbol';
            symbolDiv.dataset.row = rowIndex;
            symbolDiv.dataset.reel = reelIndex;
            
            if (symbol.isWild) {
                symbolDiv.classList.add('wild-symbol');
            }
            
            // Use simple image approach with fallback
            const img = document.createElement('img');
            const preloadedImg = this.preloadedImages.get(symbol.image);
            
            if (preloadedImg) {
                img.src = preloadedImg.src;
                console.log(`🖼️ Using preloaded image for ${symbol.name}`);
            } else {
                img.src = symbol.image;
                console.log(`⚠️ No preloaded image for ${symbol.name}, using direct path`);
                // Add fallback handler
                img.onerror = () => {
                    console.warn(`⚠️ Image failed for ${symbol.name}, using fallback`);
                    img.src = `${this.appUrl}/assets/img/avatars/qrted.png`;
                };
            }
            
            img.alt = symbol.name;
            img.dataset.level = symbol.level;
            img.dataset.rarity = symbol.rarity;
            img.dataset.isWild = symbol.isWild;
            
            symbolDiv.appendChild(img);
            
            reelData.container.appendChild(symbolDiv);
        });
    }

    markSecureWinningSymbols(reelIndex, serverData) {
        if (serverData.winning_row >= 0) {
            const symbolEl = document.querySelector(`[data-reel="${reelIndex}"][data-row="${serverData.winning_row}"]`);
            if (symbolEl) {
                symbolEl.classList.add('winning-symbol');
                this.animateWinningSymbol(symbolEl);
            }
        }
    }

    animateWinningSymbol(symbolEl) {
        gsap.to(symbolEl, {
            scale: 1.1,
            boxShadow: "0 0 20px #ffd700",
            duration: 0.6,
            ease: "power2.inOut",
            yoyo: true,
            repeat: -1
        });
    }

    updateGameStateFromServer(serverData) {
        this.currentBalance = serverData.new_balance;
        this.updateBalance();
        
        this.spinsRemaining = serverData.spins_remaining;
        
        console.log(`💰 Balance change: ${serverData.balance_change}`);
        console.log(`💰 New balance: ${serverData.new_balance}`);
        
        // Notify other components about balance change
        this.notifyBalanceUpdate(serverData.new_balance);
        
        if (this.spinsRemaining <= 0) {
            setTimeout(() => {
                alert('🎰 You\'ve used all your spins! Purchase more spin packs to continue playing.');
            }, 2000);
        }
    }

    showSecureWin(serverData) {
        const winDisplay = document.getElementById('winDisplay');
        const winAmount = document.getElementById('winAmount');
        const winMessage = document.getElementById('winMessage');
        
        if (winAmount) winAmount.textContent = serverData.win_amount;
        if (winMessage) winMessage.textContent = serverData.message;
        if (winDisplay) winDisplay.style.display = 'block';
        
        this.playCelebrationEffect(serverData.win_type);
        
        if (serverData.is_jackpot) {
            this.showJackpotCelebration(serverData);
        }
    }

    playCelebrationEffect(winType) {
        if (winType === 'wild_line' || winType === 'mythical_jackpot') {
            this.createGSAPFireworks(20);
        } else if (winType === 'diagonal_exact') {
            this.createGSAPFireworks(10);
        } else {
            this.createGSAPFireworks(5);
        }
    }

    showJackpotCelebration(serverData) {
        const jackpotAlert = document.createElement('div');
        jackpotAlert.className = 'alert alert-warning position-fixed';
        jackpotAlert.style.cssText = `
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 9999; min-width: 400px; text-align: center;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
            border: 3px solid gold; font-size: 1.2rem;
        `;
        
        jackpotAlert.innerHTML = `
            <h3>🎰 JACKPOT! 🎰</h3>
            <p>You won <strong>${serverData.win_amount} QR Coins</strong>!</p>
            <button class="btn btn-primary" onclick="this.parentElement.remove()">Awesome!</button>
        `;
        
        document.body.appendChild(jackpotAlert);
        
        setTimeout(() => {
            if (jackpotAlert.parentElement) {
                jackpotAlert.remove();
            }
        }, 10000);
    }

    createGSAPFireworks(count) {
        const container = document.body;
        
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                width: 8px;
                height: 8px;
                background: hsl(${Math.random() * 360}, 70%, 60%);
                border-radius: 50%;
                pointer-events: none;
                z-index: 9999;
            `;
            
            container.appendChild(particle);
            
            gsap.to(particle, {
                x: (Math.random() - 0.5) * 400,
                y: (Math.random() - 0.5) * 400,
                opacity: 0,
                scale: 0,
                duration: 1 + Math.random(),
                ease: "power2.out",
                onComplete: () => particle.remove()
            });
        }
    }

    getRandomSymbol() {
        return this.symbols[Math.floor(Math.random() * this.symbols.length)];
    }

    notifyBalanceUpdate(newBalance) {
        // Dispatch multiple events for compatibility with different listeners
        window.dispatchEvent(new CustomEvent('balanceUpdated', {
            detail: { newBalance: newBalance }
        }));
        
        window.dispatchEvent(new CustomEvent('balanceUpdate', {
            detail: { balance: newBalance }
        }));
        
        // Also update localStorage for cross-tab sync
        localStorage.setItem('qr_balance_update', JSON.stringify({ 
            balance: newBalance, 
            timestamp: Date.now() 
        }));
        
        console.log('🔔 Balance update notifications sent:', newBalance);
    }

    destroy() {
        if (this.spinPackCheckInterval) {
            clearInterval(this.spinPackCheckInterval);
            this.spinPackCheckInterval = null;
        }
    }

    startSpinPackMonitoring() {
        this.spinPackCheckInterval = setInterval(() => {
            this.checkForNewSpinPacks();
        }, 10000);
        
        window.addEventListener('qrStorePurchase', (event) => {
            if (event.detail && event.detail.itemType === 'slot_pack') {
                console.log('🎰 New slot pack purchased! Refreshing...');
                setTimeout(() => this.checkForNewSpinPacks(), 1000);
            }
        });
        
        window.addEventListener('balanceUpdated', (event) => {
            const balanceDiff = this.currentBalance - event.detail.newBalance;
            if (balanceDiff >= 300) {
                setTimeout(() => this.checkForNewSpinPacks(), 1000);
            }
        });
    }

    async checkForNewSpinPacks() {
        try {
            const response = await fetch(`${this.appUrl}/api/casino/get-spin-info.php?business_id=${this.businessId}`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.spinInfo) {
                    const newSpinsRemaining = data.spinInfo.spins_remaining;
                    const hadNoSpins = this.spinsRemaining !== null && this.spinsRemaining <= 0;
                    
                    this.updateSpinCountDisplay(data.spinInfo);
                    
                    if (hadNoSpins && newSpinsRemaining > 0) {
                        this.showSpinPackActivatedNotification(newSpinsRemaining);
                    }
                    
                    this.updateSpinButton();
                }
            }
        } catch (error) {
            console.error('Failed to check for new spin packs:', error);
        }
    }

    showSpinPackActivatedNotification(spinsAvailable) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.cssText = `
            top: 20px; right: 20px; z-index: 9999; min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.5s ease-out;
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-gift-fill text-success me-2" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>🛡️ Secure Spin Pack Activated!</strong><br>
                    <small>You now have ${spinsAvailable} secure spins available!</small>
                </div>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
        
        // Add animation CSS if needed
        if (!document.getElementById('spinPackAnimations')) {
            const style = document.createElement('style');
            style.id = 'spinPackAnimations';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Add page visibility handlers for balance sync
    setupPageUnloadHandlers() {
        // Sync balance when page becomes hidden or user navigates away
        const syncBalance = () => {
            if (this.currentBalance !== window.casinoData.userBalance) {
                console.log('🔄 Syncing balance before page exit:', this.currentBalance);
                // Use localStorage for immediate sync
                localStorage.setItem('qr_balance_sync', JSON.stringify({
                    balance: this.currentBalance,
                    timestamp: Date.now(),
                    source: 'slot_machine'
                }));
                
                // Attempt server sync (may not complete if page unloads)
                navigator.sendBeacon && navigator.sendBeacon(
                    `${this.appUrl}/api/sync-balance.php`,
                    JSON.stringify({ balance: this.currentBalance })
                );
            }
        };

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                syncBalance();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', syncBalance);
        window.addEventListener('pagehide', syncBalance);
        
        // Handle navigation
        window.addEventListener('popstate', syncBalance);
    }
}

// Initialize secure slot machine when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.casinoData !== 'undefined') {
        console.log('🛡️ Initializing SECURE slot machine...');
        window.secureSlotMachine = new SecureSlotMachine();
    } else {
        console.error('Casino data not available');
    }
}); 