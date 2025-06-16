/**
 * QR Coin Casino - Slot Machine Game (GSAP Optimized)
 * Professional-grade smooth animations with GSAP
 * Enhanced with Wild symbols and 5-ways-to-win
 */

class SlotMachine {
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
        });
    }

    getResponsiveSymbolHeight() {
        const width = window.innerWidth;
        if (width <= 480) return 40;
        if (width <= 768) return 48;
        return 58;
    }

    initializeSymbols() {
        // Use static high-end rare avatars for optimal performance
        this.symbols = [
            {
                image: `${this.appUrl}/assets/img/avatars/qrLordPixel.png`,
                name: 'Lord Pixel',
                level: 10,
                value: 100,
                rarity: 'mythical',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrClayton.png`,
                name: 'QR Clayton',
                level: 9,
                value: 50,
                rarity: 'legendary',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrEasybake.png`,
                name: 'QR Easybake',
                level: 8,
                value: 30,
                rarity: 'wild',
                isWild: true // WILD SYMBOL!
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrned.png`,
                name: 'QR Ned',
                level: 7,
                value: 20,
                rarity: 'epic',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qred.png`,
                name: 'QR Ed',
                level: 6,
                value: 15,
                rarity: 'rare',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrmike.png`,
                name: 'QR Mike',
                level: 5,
                value: 10,
                rarity: 'rare',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrjames.png`,
                name: 'QR James',
                level: 4,
                value: 8,
                rarity: 'uncommon',
                isWild: false
            },
            {
                image: `${this.appUrl}/assets/img/avatars/qrted.png`,
                name: 'QR Ted',
                level: 3,
                value: 5,
                rarity: 'common',
                isWild: false
            }
        ];
    }

    async preloadImages() {
        console.log('🎰 Preloading slot machine images...');
        const loadPromises = this.symbols.map(symbol => {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    this.preloadedImages.set(symbol.image, img);
                    console.log(`✅ Loaded: ${symbol.name}${symbol.isWild ? ' (WILD)' : ''}`);
                    resolve(img);
                };
                img.onerror = () => {
                    console.warn(`❌ Failed to load: ${symbol.name}, using fallback`);
                    // Create fallback image
                    const fallbackImg = new Image();
                    fallbackImg.src = `${this.appUrl}/assets/img/avatars/qrted.png`;
                    fallbackImg.onload = () => {
                        this.preloadedImages.set(symbol.image, fallbackImg);
                        symbol.image = fallbackImg.src; // Update symbol to use fallback
                        resolve(fallbackImg);
                    };
                };
                img.src = symbol.image;
            });
        });

        try {
            await Promise.all(loadPromises);
            this.isImagesLoaded = true;
            console.log('🎉 All slot machine images loaded successfully!');
            
            // Hide loading indicator and show slot machine
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('slotMachine').style.display = 'block';
            
        } catch (error) {
            console.error('Error preloading images:', error);
            this.isImagesLoaded = true; // Continue anyway
            
            // Still show the slot machine even if some images failed
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('slotMachine').style.display = 'block';
        }
    }

    initializeReels() {
        if (!this.isImagesLoaded) {
            console.warn('Images not loaded yet, retrying...');
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
            
            // Create symbol elements with preloaded images
            container.innerHTML = '';
            const symbolsPerReel = 20; // More symbols for smoother animation
            
            for (let j = 0; j < symbolsPerReel; j++) {
                const symbolDiv = document.createElement('div');
                symbolDiv.className = 'slot-symbol';
                
                const img = document.createElement('img');
                const symbol = this.symbols[j % this.symbols.length];
                
                // Use preloaded image
                const preloadedImg = this.preloadedImages.get(symbol.image);
                if (preloadedImg) {
                    img.src = preloadedImg.src;
                } else {
                    img.src = symbol.image;
                }
                
                img.alt = symbol.name;
                img.dataset.level = symbol.level;
                img.dataset.value = symbol.value;
                img.dataset.rarity = symbol.rarity;
                img.dataset.isWild = symbol.isWild;
                img.style.opacity = '1'; // Ensure visibility
                
                // Add wild styling
                if (symbol.isWild) {
                    symbolDiv.classList.add('wild-symbol');
                }
                
                symbolDiv.appendChild(img);
                container.appendChild(symbolDiv);
                
                this.reelElements[i-1].symbols.push({
                    element: symbolDiv,
                    img: img,
                    symbol: symbol
                });
            }
            
            // Set initial position with GSAP
            gsap.set(container, { y: 0 });
        }
        
        console.log('🎰 Reels initialized with GSAP and Wild symbols');
    }

    bindEvents() {
        document.getElementById('spinButton').addEventListener('click', () => {
            if (!this.isSpinning) {
                this.spin();
            }
        });
    }

    bindResizeHandler() {
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.symbolHeight = this.getResponsiveSymbolHeight();
                
                // Update all symbol heights
                document.querySelectorAll('.slot-symbol').forEach(symbol => {
                    symbol.style.height = this.symbolHeight + 'px';
                });
                
                // Reinitialize positioning
                this.reelElements.forEach(reelData => {
                    gsap.set(reelData.container, { y: -this.symbolHeight });
                });
            }, 250);
        });
    }

    updateBalance() {
        const balanceElement = document.getElementById('currentBalance');
        if (balanceElement) {
            balanceElement.textContent = this.currentBalance.toLocaleString();
        }
    }

    updateSpinCountDisplay(spinInfo) {
        // Update main spin count display
        const spinsUsedElement = document.getElementById('spinsUsed');
        const totalSpinsElement = document.getElementById('totalSpins');
        const bonusSpinsElement = document.getElementById('bonusSpins');
        const bonusSpinsText = document.getElementById('bonusSpinsText');
        const extraSpinsDisplay = document.getElementById('extraSpinsDisplay');
        const spinPackAlert = document.getElementById('spinPackAlert');
        
        if (spinInfo) {
            this.spinsRemaining = spinInfo.spins_remaining;
            
            // Update spin counts
            if (spinsUsedElement) spinsUsedElement.textContent = spinInfo.spins_used;
            if (totalSpinsElement) totalSpinsElement.textContent = spinInfo.total_spins;
            
            // Update bonus spins display
            if (spinInfo.bonus_spins > 0) {
                if (bonusSpinsElement) bonusSpinsElement.textContent = spinInfo.bonus_spins;
                if (bonusSpinsText) bonusSpinsText.style.display = 'inline';
                if (extraSpinsDisplay) extraSpinsDisplay.textContent = spinInfo.bonus_spins;
                if (spinPackAlert) spinPackAlert.style.display = 'block';
            } else {
                if (bonusSpinsText) bonusSpinsText.style.display = 'none';
                if (spinPackAlert) spinPackAlert.style.display = 'none';
            }
            
            // Update active packs list
            const activePacksList = document.getElementById('activePacksList');
            if (activePacksList && spinInfo.active_packs && spinInfo.active_packs.length > 0) {
                let packsHtml = '<br><small>';
                spinInfo.active_packs.forEach(pack => {
                    packsHtml += `• ${pack.name}`;
                    if (pack.expires_at) {
                        const expireDate = new Date(pack.expires_at);
                        packsHtml += ` (expires ${expireDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })})`;
                    }
                    packsHtml += '<br>';
                });
                packsHtml += '</small>';
                activePacksList.innerHTML = packsHtml;
            }
        }
    }

    async fetchAndUpdateSpinInfo() {
        try {
            const response = await fetch(`${this.appUrl}/api/casino/get-spin-info.php`);
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateSpinCountDisplay(data.spinInfo);
                    return data.spinInfo;
                }
            }
        } catch (error) {
            console.error('Failed to fetch spin info:', error);
        }
        return null;
    }

    updateSpinButton() {
        const spinButton = document.getElementById('spinButton');
        const betInputs = document.querySelectorAll('input[name="betAmount"]');
        let betAmount = 1; // Default bet
        
        // Get selected bet amount
        betInputs.forEach(input => {
            if (input.checked) {
                betAmount = parseInt(input.value);
            }
        });
        
        // Check if user has spins remaining (if using spin packs)
        if (this.spinsRemaining !== null && this.spinsRemaining <= 0) {
            spinButton.disabled = true;
            spinButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>No Spins Left - Buy Spin Packs!';
            spinButton.classList.remove('btn-danger');
            spinButton.classList.add('btn-secondary');
            return;
        }
        
        if (this.isSpinning) {
            spinButton.disabled = true;
            spinButton.innerHTML = '<i class="bi bi-arrow-clockwise spin-icon me-1"></i>Spinning...';
            spinButton.classList.remove('btn-secondary');
            spinButton.classList.add('btn-danger');
        } else if (betAmount > this.currentBalance) {
            spinButton.disabled = true;
            spinButton.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Insufficient Funds';
            spinButton.classList.remove('btn-danger');
            spinButton.classList.add('btn-secondary');
        } else {
            spinButton.disabled = false;
            spinButton.innerHTML = '<i class="bi bi-dice-5-fill me-2"></i>SPIN';
            spinButton.classList.remove('btn-secondary');
            spinButton.classList.add('btn-danger');
        }
    }

    async spin() {
        if (this.isSpinning) return;
        
        this.isSpinning = true;
        this.updateSpinButton();
        
        // Clear any previous win displays
        document.getElementById('winDisplay').style.display = 'none';
        document.querySelectorAll('.winning-symbol').forEach(symbol => {
            symbol.classList.remove('winning-symbol');
            gsap.killTweensOf(symbol);
        });
        
        // Get selected bet amount from radio buttons
        let betAmount = 1; // Default bet
        const betInputs = document.querySelectorAll('input[name="betAmount"]');
        betInputs.forEach(input => {
            if (input.checked) {
                betAmount = parseInt(input.value);
            }
        });
        const results = this.generateSpinResults();
        
        console.log('🎰 Starting spin with results:', results.map(r => `${r.name}${r.isWild ? ' (WILD)' : ''}`));
        
        // Start the spinning animation
        await this.startGSAPSpin();
        
        // Stop reels with final results
        await this.stopReelsWithGSAP(results);
        
        // Show results and handle API
        setTimeout(() => {
            // Get the actual displayed symbols for win checking
            const actualDisplayed = this.getDisplayedSymbols();
            console.log('Generated results:', results.map(r => `${r.name}${r.isWild ? ' (WILD)' : ''}`));
            console.log('Actually displayed:', actualDisplayed.map(r => `${r.name}${r.isWild ? ' (WILD)' : ''}`));
            
            // Use the actually displayed symbols for win checking
            const winData = this.checkWin(actualDisplayed.length === 3 ? actualDisplayed : results, betAmount);
            if (winData.isWin) {
                this.showWin(winData);
            }
            
            // Record the play (this will handle balance updates via API)
            this.recordPlay(betAmount, winData.amount, actualDisplayed.length === 3 ? actualDisplayed : results);
            
            this.isSpinning = false;
            this.updateSpinButton();
        }, 500);
    }

    async startGSAPSpin() {
        // Add spinning glow effect
        this.reelElements.forEach(reelData => {
            reelData.reel.classList.add('spinning');
        });

        // Create fast spinning timeline
        const tl = gsap.timeline();
        
        this.reelElements.forEach((reelData, index) => {
            const speed = 0.05 + (index * 0.01); // Slightly different speeds
            
            tl.to(reelData.container, {
                y: -this.symbolHeight * 20, // Spin through 20 symbols
                duration: 2,
                ease: "none",
                repeat: -1,
                modifiers: {
                    y: (y) => {
                        // Create infinite loop
                        const parsed = parseFloat(y);
                        const max = this.symbolHeight * 20;
                        return (parsed % max) + "px";
                    }
                }
            }, index * 0.1); // Stagger start times
        });

        // Let it spin for 2.5 seconds
        await new Promise(resolve => setTimeout(resolve, 2500));
        
        // Kill the infinite animation
        tl.kill();
    }

    async stopReelsWithGSAP(results) {
        // Check if we have a winning combination to ensure visual alignment
        const winData = this.checkWin(results, 1); // Use bet amount 1 just for checking
        
        const stopPromises = this.reelElements.map((reelData, index) => {
            return new Promise(resolve => {
                setTimeout(() => {
                    this.stopSingleReelGSAP(reelData, results[index], index + 1, winData);
                    resolve();
                }, index * 400); // 400ms delay between stops
            });
        });

        await Promise.all(stopPromises);
    }

    stopSingleReelGSAP(reelData, result, reelIndex, winData) {
        // Remove spinning class
        reelData.reel.classList.remove('spinning');
        
        // Build final reel state
        this.buildFinalReel(reelData, result, reelIndex, winData);
        
        // Animate to final position with bounce
        gsap.to(reelData.container, {
            y: 0, // Show all 3 symbols properly positioned
            duration: 0.8,
            ease: "back.out(1.7)",
            onComplete: () => {
                // Add winning glow effect only if this reel contributes to a win
                if (winData && winData.isWin) {
                    gsap.to(reelData.reel, {
                        boxShadow: "0 0 30px rgba(255, 193, 7, 0.8)",
                        duration: 0.3,
                        yoyo: true,
                        repeat: 1
                    });
                }
            }
        });
    }

    buildFinalReel(reelData, result, reelIndex, winData) {
        // Clear and rebuild reel with 3 visible rows
        reelData.container.innerHTML = '';
        
        // Create 3 visible symbols per reel for proper 3x3 grid
        const symbolPositions = [
            result.topSymbol || this.getRandomSymbol(),    // Top row
            result.middleSymbol || result,                 // Middle row (main result)
            result.bottomSymbol || this.getRandomSymbol()  // Bottom row
        ];
        
        symbolPositions.forEach((symbol, rowIndex) => {
            const symbolDiv = document.createElement('div');
            symbolDiv.className = 'slot-symbol';
            symbolDiv.dataset.row = rowIndex;
            symbolDiv.dataset.reel = reelIndex;
            
            // Add wild styling
            if (symbol.isWild) {
                symbolDiv.classList.add('wild-symbol');
            }
            
            // Mark winning symbols based on win pattern and position
            if (winData && winData.isWin) {
                this.markWinningSymbolInGrid(symbolDiv, symbol, reelIndex, rowIndex, winData);
            }
            
            const img = document.createElement('img');
            const preloadedImg = this.preloadedImages.get(symbol.image);
            img.src = preloadedImg ? preloadedImg.src : symbol.image;
            img.alt = symbol.name;
            img.dataset.level = symbol.level;
            img.dataset.value = symbol.value;
            img.dataset.rarity = symbol.rarity;
            img.dataset.isWild = symbol.isWild;
            
            symbolDiv.appendChild(img);
            reelData.container.appendChild(symbolDiv);
        });
    }

    markWinningSymbolInGrid(symbolDiv, symbol, reelIndex, rowIndex, winData) {
        const winType = winData.type;
        let shouldMark = false;
        
        console.log(`🎯 Checking grid position [${reelIndex}, ${rowIndex}] for ${winType}`);
        
        // Determine which positions should be marked based on win type in 3x3 grid
        switch (winType) {
            case 'straight_line':
            case 'rare_jackpot':
            case 'mythical_jackpot':
            case 'rarity_line':
            case 'wild_line':
                // Mark the winning row (could be top, middle, or bottom)
                shouldMark = (rowIndex === winData.winningRow);
                console.log(`${shouldMark ? '✅' : '❌'} Position [${reelIndex}, ${rowIndex}] for ${winType} (winning row: ${winData.winningRow})`);
                break;
                
            case 'diagonal_top_left':
                // Top-left to bottom-right diagonal: [0,0], [1,1], [2,2]
                shouldMark = (reelIndex === rowIndex);
                console.log(`${shouldMark ? '✅' : '❌'} Position [${reelIndex}, ${rowIndex}] for diagonal_top_left`);
                break;
                
            case 'diagonal_top_right':
                // Top-right to bottom-left diagonal: [0,2], [1,1], [2,0]
                shouldMark = (reelIndex + rowIndex === 2);
                console.log(`${shouldMark ? '✅' : '❌'} Position [${reelIndex}, ${rowIndex}] for diagonal_top_right`);
                break;
                
            case 'diagonal_exact':
            case 'diagonal_rarity':
                // Check if this position is part of the winning diagonal
                const isDiagonalTL = (reelIndex === rowIndex);
                const isDiagonalTR = (reelIndex + rowIndex === 2);
                shouldMark = isDiagonalTL || isDiagonalTR;
                console.log(`${shouldMark ? '✅' : '❌'} Position [${reelIndex}, ${rowIndex}] for ${winType} (TL: ${isDiagonalTL}, TR: ${isDiagonalTR})`);
                break;
        }
        
        if (shouldMark) {
            symbolDiv.classList.add('winning-symbol');
            console.log(`🌟 Marked grid position [${reelIndex}, ${rowIndex}]: ${symbol.name}${symbol.isWild ? ' (WILD)' : ''}`);
        }
    }

    getRandomSymbol() {
        return this.symbols[Math.floor(Math.random() * this.symbols.length)];
    }

    getDisplayedSymbols() {
        const displayed = [];
        
        // Get the center symbol from each reel (the winning position)
        this.reelElements.forEach((reelData, index) => {
            const symbols = reelData.container.querySelectorAll('.slot-symbol');
            if (symbols.length >= 2) {
                // The center symbol is at index 1 (0=top, 1=center, 2=bottom)
                const centerSymbol = symbols[1];
                const img = centerSymbol.querySelector('img');
                if (img) {
                    // Find matching symbol from our symbols array
                    const matchingSymbol = this.symbols.find(s => 
                        img.src.includes(s.image.split('/').pop()) || 
                        s.image.includes(img.src.split('/').pop())
                    );
                    if (matchingSymbol) {
                        displayed.push({...matchingSymbol});
                    }
                }
            }
        });
        
        return displayed;
    }

    generateSpinResults() {
        // First, decide if this should be a winning spin based on probability
        const winChance = Math.random();
        
        // Balanced casino win probability: ~15% chance for any win (better player experience)
        if (winChance < 0.15) {
            return this.generateWinningResults();
        } else {
            return this.generateLosingResults();
        }
    }
    
    generateWinningResults() {
        const winType = Math.random();
        
        // Create a 3x3 grid for the slot machine
        let grid = [
            [this.getRandomSymbol(), this.getRandomSymbol(), this.getRandomSymbol()], // Row 0 (top)
            [this.getRandomSymbol(), this.getRandomSymbol(), this.getRandomSymbol()], // Row 1 (middle)
            [this.getRandomSymbol(), this.getRandomSymbol(), this.getRandomSymbol()]  // Row 2 (bottom)
        ];
        
        if (winType < 0.4) {
            // 40% of wins are horizontal line wins
            const winningRow = Math.floor(Math.random() * 3); // 0, 1, or 2
            const winningSymbol = this.weightedRandomSelect(this.symbols.filter(s => !s.isWild), this.symbols.filter(s => !s.isWild).map(s => Math.max(1, 5 - s.level)));
            
            // Fill the winning row with the same symbol (possibly with wilds)
            const useWild = Math.random() < 0.2; // 20% chance to include wild
            const wildSymbol = this.symbols.find(s => s.isWild);
            
            for (let col = 0; col < 3; col++) {
                if (useWild && Math.random() < 0.3) {
                    grid[winningRow][col] = {...wildSymbol};
                } else {
                    grid[winningRow][col] = {...winningSymbol};
                }
            }
            
            return this.convertGridToResults(grid, winningRow, 'horizontal');
            
        } else if (winType < 0.7) {
            // 30% of wins are diagonal wins
            const isDiagonalTL = Math.random() < 0.5; // Top-left to bottom-right vs top-right to bottom-left
            const winningSymbol = this.weightedRandomSelect(this.symbols.filter(s => !s.isWild), this.symbols.filter(s => !s.isWild).map(s => Math.max(1, 5 - s.level)));
            
            if (isDiagonalTL) {
                // Top-left to bottom-right diagonal: [0,0], [1,1], [2,2]
                grid[0][0] = {...winningSymbol};
                grid[1][1] = {...winningSymbol};
                grid[2][2] = {...winningSymbol};
            } else {
                // Top-right to bottom-left diagonal: [0,2], [1,1], [2,0]
                grid[0][2] = {...winningSymbol};
                grid[1][1] = {...winningSymbol};
                grid[2][0] = {...winningSymbol};
            }
            
            return this.convertGridToResults(grid, -1, isDiagonalTL ? 'diagonal_tl' : 'diagonal_tr');
            
        } else if (winType < 0.9) {
            // 20% of wins are rarity line wins
            const winningRow = Math.floor(Math.random() * 3);
            const rarity = ['rare', 'epic', 'legendary'][Math.floor(Math.random() * 3)];
            const sameRaritySymbols = this.symbols.filter(s => s.rarity === rarity);
            
            if (sameRaritySymbols.length >= 3) {
                for (let col = 0; col < 3; col++) {
                    grid[winningRow][col] = {...sameRaritySymbols[Math.floor(Math.random() * sameRaritySymbols.length)]};
                }
                return this.convertGridToResults(grid, winningRow, 'rarity');
            }
        } else {
            // 10% of wins are wild-based wins
            const wildSymbol = this.symbols.find(s => s.isWild);
            const winningRow = Math.floor(Math.random() * 3);
            
            // Place 2-3 wilds in the winning row
            const wildCount = Math.random() < 0.5 ? 2 : 3;
            const positions = [0, 1, 2].sort(() => Math.random() - 0.5).slice(0, wildCount);
            
            positions.forEach(pos => {
                grid[winningRow][pos] = {...wildSymbol};
            });
            
            return this.convertGridToResults(grid, winningRow, 'wild');
        }
        
        // Fallback to horizontal line win
        const winningSymbol = this.getRandomSymbol();
        grid[1][0] = {...winningSymbol};
        grid[1][1] = {...winningSymbol};
        grid[1][2] = {...winningSymbol};
        
        return this.convertGridToResults(grid, 1, 'horizontal');
    }
    
    convertGridToResults(grid, winningRow, winType) {
        // Convert 3x3 grid to the format expected by the slot machine
        const results = [];
        
        for (let col = 0; col < 3; col++) {
            results.push({
                topSymbol: grid[0][col],
                middleSymbol: grid[1][col],
                bottomSymbol: grid[2][col],
                winningRow: winningRow,
                winType: winType
            });
        }
        
        return results;
    }
    
    generateLosingResults() {
        const results = [];
        
        // Generate 3 symbols that definitely don't match any winning pattern
        for (let i = 0; i < 3; i++) {
            // Reduce wild appearance in losing combinations
            let attempts = 0;
            let symbol;
            do {
                symbol = this.getRandomSymbol();
                attempts++;
            } while (symbol.isWild && attempts < 5 && Math.random() < 0.7); // Reduce wild chance in losses
            
            const weights = this.symbols.map(s => Math.max(1, 5 - s.level));
            const randomSymbol = this.weightedRandomSelect(this.symbols, weights);
            results.push({...randomSymbol});
        }
        
        // Make sure it's actually a losing combination
        // If we accidentally created a win, modify it
        const testWin = this.checkWin(results, 1);
        if (testWin.isWin) {
            // Make the middle symbol different to break any winning pattern
            results[1] = this.getRandomSymbolDifferentFrom([results[0], results[2]]);
        }
        
        return results;
    }
    
    getRandomSymbolDifferentFrom(excludeSymbols) {
        let attempts = 0;
        let symbol;
        
        do {
            symbol = this.getRandomSymbol();
            attempts++;
        } while (attempts < 10 && excludeSymbols.some(excluded => 
            excluded.image === symbol.image || 
            (excluded.rarity === symbol.rarity && !symbol.isWild)
        ));
        
        return {...symbol};
    }

    weightedRandomSelect(items, weights) {
        const totalWeight = weights.reduce((sum, weight) => sum + weight, 0);
        let random = Math.random() * totalWeight;
        
        for (let i = 0; i < items.length; i++) {
            random -= weights[i];
            if (random <= 0) {
                return items[i];
            }
        }
        
        return items[items.length - 1];
    }

    checkWin(results, betAmount) {
        const [reel1, reel2, reel3] = results;
        
        // Convert results to 3x3 grid format
        const grid = [
            [reel1.topSymbol || reel1, reel2.topSymbol || reel2, reel3.topSymbol || reel3],       // Row 0 (top)
            [reel1.middleSymbol || reel1, reel2.middleSymbol || reel2, reel3.middleSymbol || reel3], // Row 1 (middle)
            [reel1.bottomSymbol || reel1, reel2.bottomSymbol || reel2, reel3.bottomSymbol || reel3]  // Row 2 (bottom)
        ];
        
        console.log('🎰 Checking win for 3x3 grid:', {
            'Row 0': grid[0].map(s => `${s.name}${s.isWild ? '🌟' : ''}`),
            'Row 1': grid[1].map(s => `${s.name}${s.isWild ? '🌟' : ''}`),
            'Row 2': grid[2].map(s => `${s.name}${s.isWild ? '🌟' : ''}`)
        });

        // Helper function to check if two symbols match (considering wilds)
        const symbolsMatch = (sym1, sym2) => {
            return sym1.isWild || sym2.isWild || sym1.image === sym2.image;
        };

        const symbolsMatchRarity = (sym1, sym2) => {
            return sym1.isWild || sym2.isWild || sym1.rarity === sym2.rarity;
        };

        // Check for line matches in the 3x3 grid
        const checkLine = (line) => {
            const [s1, s2, s3] = line;
            return symbolsMatch(s1, s2) && symbolsMatch(s2, s3) && symbolsMatch(s1, s3);
        };

        // Check all possible winning lines
        const lines = [
            // Horizontal lines
            { symbols: grid[0], name: 'Top Row', type: 'horizontal', row: 0 },
            { symbols: grid[1], name: 'Middle Row', type: 'horizontal', row: 1 },
            { symbols: grid[2], name: 'Bottom Row', type: 'horizontal', row: 2 },
            // Diagonal lines
            { symbols: [grid[0][0], grid[1][1], grid[2][2]], name: 'Top-Left Diagonal', type: 'diagonal_tl', row: -1 },
            { symbols: [grid[0][2], grid[1][1], grid[2][0]], name: 'Top-Right Diagonal', type: 'diagonal_tr', row: -1 }
        ];

        // Find winning lines
        const winningLines = lines.filter(line => {
            const isWin = checkLine(line.symbols);
            if (line.type.includes('diagonal')) {
                console.log(`🔍 Checking ${line.name}:`, {
                    symbols: line.symbols.map(s => `${s.name}${s.isWild ? '🌟' : ''}`),
                    positions: line.type === 'diagonal_tl' ? '[0,0],[1,1],[2,2]' : '[0,2],[1,1],[2,0]',
                    result: isWin ? '✅ WIN' : '❌ NO WIN',
                    reason: isWin ? 'All 3 positions match!' : 'Not all 3 positions match'
                });
            }
            return isWin;
        });
        
        if (winningLines.length > 0) {
            // Use the first winning line found
            const winningLine = winningLines[0];
            const [s1, s2, s3] = winningLine.symbols;
            
            // Determine the base symbol (non-wild) for payout calculation
            const baseSymbol = s1.isWild ? (s2.isWild ? s3 : s2) : s1;
            const wildCount = winningLine.symbols.filter(s => s.isWild).length;
            
            // THREE WILDS - MEGA JACKPOT!
            if (wildCount === 3) {
                return {
                    isWin: true,
                    amount: betAmount * this.jackpotMultiplier * 2,
                    type: 'wild_line',
                    message: '🌟 TRIPLE WILD MEGA JACKPOT! 🌟',
                    winningRow: winningLine.row
                };
            }
            
            // MYTHICAL JACKPOT (Lord Pixel line)
            if (baseSymbol.rarity === 'mythical') {
                return {
                    isWin: true,
                    amount: betAmount * this.jackpotMultiplier * 1.5,
                    type: 'mythical_jackpot',
                    message: `💎 MYTHICAL ${winningLine.name} JACKPOT! 💎`,
                    winningRow: winningLine.row
                };
            }
            
            // Regular line wins
            const multiplier = baseSymbol.level >= 8 ? this.jackpotMultiplier : (baseSymbol.level * 2);
            const wildBonus = wildCount * 1;
            const diagonalBonus = winningLine.type.includes('diagonal') ? 2 : 0;
            
            return {
                isWin: true,
                amount: betAmount * (multiplier + wildBonus + diagonalBonus),
                type: winningLine.type.includes('diagonal') ? 'diagonal_exact' : 'straight_line',
                message: wildCount > 0 ? 
                    `🌟 WILD ${winningLine.name}! 🌟` : 
                    `🎯 ${winningLine.name} WIN! 🎯`,
                winningRow: winningLine.row
            };
        }

        return {
            isWin: false,
            amount: 0,
            type: 'loss',
            message: 'Line up 3 across, hit the diagonals, or get wilds!'
        };
    }

    showWin(winData) {
        const winDisplay = document.getElementById('winDisplay');
        const winMessage = document.getElementById('winMessage');
        const winAmount = document.getElementById('winAmount');
        
        winMessage.textContent = winData.message;
        winAmount.textContent = winData.amount.toLocaleString();
        
        // Animate win display with GSAP
        gsap.set(winDisplay, { scale: 0.8, opacity: 0, display: 'block' });
        gsap.to(winDisplay, {
            scale: 1,
            opacity: 1,
            duration: 0.5,
            ease: "back.out(1.7)"
        });
        
        // Highlight winning symbols with GSAP
        document.querySelectorAll('.winning-symbol').forEach(symbol => {
            gsap.to(symbol, {
                scale: 1.1,
                duration: 0.6,
                ease: "power2.inOut",
                yoyo: true,
                repeat: -1
            });
        });
        
        // Play celebration effect
        this.playCelebrationEffect(winData.type);
    }

    playCelebrationEffect(winType) {
        if (winType === 'mythical_jackpot' || winType === 'rare_jackpot' || winType === 'wild_line') {
            this.createGSAPFireworks(50);
            // Screen shake effect
            gsap.to('.slot-machine-card', {
                x: '+=5',
                duration: 0.1,
                yoyo: true,
                repeat: 5,
                ease: "power2.inOut"
            });
        } else if (winType !== 'loss') {
            this.createGSAPFireworks(20);
        }
    }

    createGSAPFireworks(count) {
        const container = document.querySelector('.slot-reels-container');
        
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: absolute;
                width: 6px;
                height: 6px;
                background: #ffd700;
                border-radius: 50%;
                pointer-events: none;
                left: 50%;
                top: 50%;
                z-index: 1000;
            `;
            
            container.appendChild(particle);
            
            // Animate with GSAP
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

    async recordPlay(betAmount, winAmount, results) {
        try {
            const response = await fetch(`${this.appUrl}/api/casino/record-play.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    business_id: this.businessId,
                    bet_amount: betAmount,
                    win_amount: winAmount,
                    results: results
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to record play');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Update balance with server response to ensure accuracy
                this.currentBalance = data.new_balance;
                this.updateBalance();
                
                // FIXED: Update remaining spins display and check if user has run out
                if (typeof data.plays_remaining !== 'undefined') {
                    this.spinsRemaining = data.plays_remaining;
                    console.log('Spins remaining after play:', data.plays_remaining);
                    
                    // Update spin count display
                    this.updateSpinCountDisplay({
                        has_spin_pack: true,
                        spins_remaining: data.plays_remaining
                    });
                    
                    // Check if user has run out of spins
                    if (data.plays_remaining <= 0) {
                        setTimeout(() => {
                            alert('🎰 You\'ve used all your spins! Purchase more spin packs to continue playing.');
                            this.updateSpinButton();
                        }, 2000);
                    }
                }
                
                // Notify other components about balance change
                this.notifyBalanceUpdate(data.new_balance);
            } else {
                throw new Error(data.error || 'Failed to record play');
            }
        } catch (error) {
            console.error('Error recording play:', error);
            alert('Error recording play: ' + error.message);
        }
    }

    notifyBalanceUpdate(newBalance) {
        // Dispatch custom event for other components to listen to
        window.dispatchEvent(new CustomEvent('balanceUpdated', {
            detail: { newBalance: newBalance }
        }));
    }

    // Cleanup function to prevent memory leaks
    destroy() {
        if (this.spinPackCheckInterval) {
            clearInterval(this.spinPackCheckInterval);
            this.spinPackCheckInterval = null;
        }
    }

    startSpinPackMonitoring() {
        // Check for new spin packs every 10 seconds
        this.spinPackCheckInterval = setInterval(() => {
            this.checkForNewSpinPacks();
        }, 10000);
        
        // Also listen for QR Store purchase events
        window.addEventListener('qrStorePurchase', (event) => {
            if (event.detail && event.detail.itemType === 'slot_pack') {
                console.log('🎰 New slot pack purchased! Refreshing spin availability...');
                setTimeout(() => {
                    this.checkForNewSpinPacks();
                }, 1000); // Small delay to ensure server has processed the purchase
            }
        });
        
        // Listen for balance updates that might indicate a spin pack purchase
        window.addEventListener('balanceUpdated', (event) => {
            // If balance decreased significantly, check for new spin packs
            const balanceDiff = this.currentBalance - event.detail.newBalance;
            if (balanceDiff >= 300) { // Minimum spin pack cost
                setTimeout(() => {
                    this.checkForNewSpinPacks();
                }, 1000);
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
                    
                    // Update spin info
                    this.updateSpinCountDisplay(data.spinInfo);
                    
                    // If user had no spins and now has spins, show notification
                    if (hadNoSpins && newSpinsRemaining > 0) {
                        this.showSpinPackActivatedNotification(newSpinsRemaining);
                    }
                    
                    // Update the spin button state
                    this.updateSpinButton();
                }
            }
        } catch (error) {
            console.error('Failed to check for new spin packs:', error);
        }
    }

    showSpinPackActivatedNotification(spinsAvailable) {
        // Create and show a notification that spin packs are now active
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.cssText = `
            top: 20px; 
            right: 20px; 
            z-index: 9999; 
            min-width: 300px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.5s ease-out;
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-gift-fill text-success me-2" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>🎰 Spin Pack Activated!</strong><br>
                    <small>You now have ${spinsAvailable} spins available!</small>
                </div>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
        
        // Add CSS animation if not already present
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
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.casinoData !== 'undefined') {
        window.slotMachine = new SlotMachine();
    } else {
        console.error('Casino data not available');
    }
}); 