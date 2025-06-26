/**
 * QR Blackjack Game - Custom Avatar Cards with QR Code Backs
 * Features QR-themed avatars on face cards and QR code designs on card backs
 */

class QRBlackjack {
    constructor() {
        // Validate that required data is available
        if (typeof window.blackjackData === 'undefined') {
            // Remove alert - just redirect to casino
            window.location.href = '/casino/';
            return;
        }
        
        this.deck = [];
        this.playerHand = [];
        this.dealerHand = [];
        this.gameState = 'betting'; // betting, playing, dealer, finished
        this.currentBet = 1;
        this.userBalance = window.blackjackData.userBalance || 0;
        
        // CRITICAL FIX: Handle null/undefined business ID properly
        this.businessId = window.blackjackData.businessId;
        if (!this.businessId || this.businessId === null || this.businessId === 'null' || isNaN(this.businessId)) {
            this.businessId = 1; // Default fallback business ID
        }
        
        this.appUrl = window.blackjackData.appUrl || '';
        
        // Card configurations with QR avatars
        this.cardConfig = {
            // Only Ace has custom QR avatar
            faceCards: {
                'A': {
                    name: 'Ace',
                    avatar: 'qrEasybake.png',
                    avatarName: 'QR Easybake',
                    value: [1, 11] // Ace can be 1 or 11
                }
            },
            suits: {
                '♠': { name: 'Spades', color: 'black', symbol: '♠' },
                '♥': { name: 'Hearts', color: 'red', symbol: '♥' },
                '♦': { name: 'Diamonds', color: 'red', symbol: '♦' },
                '♣': { name: 'Clubs', color: 'black', symbol: '♣' }
            }
        };
        
        this.initializeGame();
    }

    initializeGame() {
            businessId: this.businessId,
            businessIdType: typeof this.businessId,
            userBalance: this.userBalance,
            rawBusinessIdFromWindow: window.blackjackData.businessId
        });
        
        this.bindEvents();
        this.updateBalance();
        this.updateBetDisplay();
        this.generateDeck();
    }

    bindEvents() {
        // Betting controls
        document.querySelectorAll('input[name="betAmount"]').forEach(input => {
            input.addEventListener('change', (e) => {
                this.currentBet = parseInt(e.target.value);
                this.updateBetDisplay();
            });
        });

        // Game controls
        document.getElementById('newGameBtn').addEventListener('click', () => this.startNewGame());
        document.getElementById('hitBtn').addEventListener('click', () => this.hit());
        document.getElementById('standBtn').addEventListener('click', () => this.stand());
    }

    generateDeck() {
        this.deck = [];
        const suits = Object.keys(this.cardConfig.suits);
        const values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
        
        for (let suit of suits) {
            for (let value of values) {
                this.deck.push({
                    suit: suit,
                    value: value,
                    id: `${value}_${suit}`,
                    numericValue: this.getCardValue(value),
                    color: this.cardConfig.suits[suit].color
                });
            }
        }
        
        // Shuffle the deck
        this.shuffleDeck();
    }

    shuffleDeck() {
        for (let i = this.deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.deck[i], this.deck[j]] = [this.deck[j], this.deck[i]];
        }
    }

    getCardValue(value) {
        if (value === 'A') return 11; // Start with 11, adjust later if needed
        if (['K', 'Q', 'J'].includes(value)) return 10;
        return parseInt(value);
    }

    createCardElement(card, isBack = false) {
        
        const cardDiv = document.createElement('div');
        cardDiv.className = 'card-element';
        cardDiv.dataset.cardId = card.id;
        cardDiv.style.cssText = `
            width: 120px;
            height: 168px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            margin: 5px;
            overflow: hidden;
        `;
        
        if (isBack) {
            // Card back with QR code design
            cardDiv.style.background = 'linear-gradient(135deg, #1a1a1a, #2d2d2d)';
            cardDiv.style.border = '2px solid #ffd700';
            cardDiv.innerHTML = `
                <div style="color: #ffd700; font-size: 1.2rem; text-align: center; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    QR<br>CASINO
                </div>
            `;
        } else {
            // Use your custom card images
            const filename = this.getCardFilename(card.value, card.suit);
            
            cardDiv.innerHTML = `
                <img src="../casino/${filename}" 
                     alt="${card.value} of ${this.getSuitName(card.suit)}" 
                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;"
            `;
        }
        
        return cardDiv;
    }

    getCardFilename(value, suit) {
        // Convert card value to filename format
        let cardName = value.toLowerCase();
        if (value === 'A') cardName = 'ace';
        if (value === 'K') cardName = 'king';
        if (value === 'Q') cardName = 'queen';
        if (value === 'J') cardName = 'jack';
        
        // Convert suit to filename format
        const suitName = this.getSuitName(suit);
        
        // Handle the typo in ace_of_dimonds.png
        if (cardName === 'ace' && suitName === 'diamonds') {
            return 'ace_of_dimonds.png';
        }
        
        return `${cardName}_of_${suitName}.png`;
    }

    getSuitName(suit) {
        const suitMap = {
            '♠': 'spades',
            '♥': 'hearts', 
            '♦': 'diamonds',
            '♣': 'clubs'
        };
        return suitMap[suit] || 'spades';
    }

    startNewGame() {
        if (this.userBalance < this.currentBet) {
            this.updateGameStatus('❌ Insufficient balance for this bet!', 'error');
            return;
        }
        
        // DON'T deduct balance optimistically - let the server handle it when game ends
        // This prevents balance restoration issues on losses
        
        // Reset game state
        this.playerHand = [];
        this.dealerHand = [];
        this.gameState = 'playing';
        
        // Clear previous cards
        document.getElementById('playerCards').innerHTML = '';
        document.getElementById('dealerCards').innerHTML = '';
        
        // Generate new deck if needed
        if (this.deck.length < 10) {
            this.generateDeck();
        }
        
        // Deal initial cards
        this.dealInitialCards();
        
        // Update UI
        this.updateGameButtons();
        this.updateGameStatus(`🎯 Bet placed: ${this.currentBet} coins! Hit or Stand?`, 'playing');
        
    }

    dealInitialCards() {
        // Player gets 2 face-up cards
        this.playerHand.push(this.deck.pop());
        this.playerHand.push(this.deck.pop());
        
        // Dealer gets 2 cards (1 face-up, 1 face-down)
        this.dealerHand.push(this.deck.pop());
        this.dealerHand.push(this.deck.pop());
        
        // Display cards
        this.displayPlayerCards();
        this.displayDealerCards(true); // true = hide second card
        
        // Check for blackjack
        if (this.getHandValue(this.playerHand) === 21) {
            this.gameState = 'dealer';
            this.dealerTurn();
        }
    }

    displayPlayerCards() {
        const container = document.getElementById('playerCards');
        
        container.innerHTML = '';
        
        this.playerHand.forEach((card, index) => {
            const cardElement = this.createCardElement(card, false);
            
            cardElement.style.animationDelay = `${index * 200}ms`;
            cardElement.classList.add('card-flip');
            container.appendChild(cardElement);
            
        });
        
        this.updateScore('player');
    }

    displayDealerCards(hideSecond = false) {
        const container = document.getElementById('dealerCards');
        
        container.innerHTML = '';
        
        this.dealerHand.forEach((card, index) => {
            const shouldHide = hideSecond && index === 1;
            
            const cardElement = this.createCardElement(card, shouldHide);
            
            cardElement.style.animationDelay = `${index * 200}ms`;
            cardElement.classList.add('card-flip');
            container.appendChild(cardElement);
            
        });
        
        this.updateScore('dealer', hideSecond);
    }

    hit() {
        if (this.gameState !== 'playing') return;
        
        // Add card to player hand
        this.playerHand.push(this.deck.pop());
        this.displayPlayerCards();
        
        const playerValue = this.getHandValue(this.playerHand);
        
        if (playerValue > 21) {
            // Player busts
            this.gameState = 'finished';
            this.endGame('bust');
        } else if (playerValue === 21) {
            // Player has 21, dealer's turn
            this.gameState = 'dealer';
            this.dealerTurn();
        }
        
        this.updateGameButtons();
    }

    stand() {
        if (this.gameState !== 'playing') return;
        
        this.gameState = 'dealer';
        this.dealerTurn();
    }

    dealerTurn() {
        // Reveal dealer's hidden card
        this.displayDealerCards(false);
        
        // Dealer must hit on 16 and below, stand on 17 and above
        const dealerPlay = () => {
            const dealerValue = this.getHandValue(this.dealerHand);
            
            if (dealerValue < 17) {
                setTimeout(() => {
                    this.dealerHand.push(this.deck.pop());
                    this.displayDealerCards(false);
                    dealerPlay();
                }, 1000);
            } else {
                // Dealer stands, determine winner
                this.gameState = 'finished';
                this.determineWinner();
            }
        };
        
        setTimeout(dealerPlay, 1000);
    }

    determineWinner() {
        const playerValue = this.getHandValue(this.playerHand);
        const dealerValue = this.getHandValue(this.dealerHand);
        
        
        if (playerValue > 21) {
            this.endGame('bust');
        } else if (dealerValue > 21) {
            this.endGame('dealer_bust');
        } else if (playerValue === 21 && this.playerHand.length === 2) {
            this.endGame('blackjack');
        } else if (playerValue > dealerValue) {
            this.endGame('win');
        } else if (playerValue < dealerValue) {
            this.endGame('lose');
        } else {
            this.endGame('push');
        }
    }

    endGame(result) {
        let winAmount = 0;
        let message = '';
        let statusClass = '';
        
        switch (result) {
            case 'blackjack':
                winAmount = Math.floor(this.currentBet * 2.5); // 3:2 payout (bet already deducted, so this is profit + original bet back)
                message = `🃏 BLACKJACK! You win ${winAmount} coins!`;
                statusClass = 'status-win';
                break;
            case 'win':
                winAmount = this.currentBet * 2; // Return bet + winnings
                message = `🎉 You win ${winAmount} coins!`;
                statusClass = 'status-win';
                break;
            case 'dealer_bust':
                winAmount = this.currentBet * 2; // Return bet + winnings
                message = `💥 Dealer busts! You win ${winAmount} coins!`;
                statusClass = 'status-win';
                break;
            case 'push':
                winAmount = this.currentBet; // Return original bet
                message = `🤝 Push! Your ${this.currentBet} coin bet is returned.`;
                statusClass = 'status-push';
                break;
            case 'lose':
                winAmount = 0; // No winnings (bet already deducted)
                message = `😔 You lose ${this.currentBet} coins.`;
                statusClass = 'status-lose';
                break;
            case 'bust':
                winAmount = 0; // No winnings (bet already deducted)
                message = `💥 Bust! You lose ${this.currentBet} coins.`;
                statusClass = 'status-lose';
                break;
        }
        
        // Record the game on server FIRST (this will update balance properly)
        this.recordGame(result, winAmount);
        
        // Update UI (balance will be updated by recordGame when server responds)
        this.updateGameStatus(message, statusClass);
        this.updateGameButtons();
        
        // Add visual effects
        if (winAmount > 0) {
            this.addWinEffects();
        }
        
    }

    getHandValue(hand) {
        let value = 0;
        let aces = 0;
        
        for (let card of hand) {
            if (card.value === 'A') {
                aces++;
                value += 11;
            } else {
                value += this.getCardValue(card.value);
            }
        }
        
        // Adjust for aces
        while (value > 21 && aces > 0) {
            value -= 10;
            aces--;
        }
        
        return value;
    }

    updateScore(player, hideDealer = false) {
        if (player === 'player') {
            const value = this.getHandValue(this.playerHand);
            document.getElementById('playerScore').textContent = value;
            document.getElementById('playerScore').className = 
                `badge fs-6 ${value > 21 ? 'bg-danger' : value === 21 ? 'bg-success' : 'bg-info'}`;
        } else if (player === 'dealer') {
            if (hideDealer) {
                document.getElementById('dealerScore').textContent = this.getCardValue(this.dealerHand[0].value);
            } else {
                const value = this.getHandValue(this.dealerHand);
                document.getElementById('dealerScore').textContent = value;
                document.getElementById('dealerScore').className = 
                    `badge text-dark fs-6 ${value > 21 ? 'bg-danger' : value === 21 ? 'bg-success' : 'bg-warning'}`;
            }
        }
    }

    updateGameButtons() {
        const hitBtn = document.getElementById('hitBtn');
        const standBtn = document.getElementById('standBtn');
        const newGameBtn = document.getElementById('newGameBtn');
        
        if (this.gameState === 'playing') {
            hitBtn.disabled = false;
            standBtn.disabled = false;
            newGameBtn.textContent = 'New Game';
        } else {
            hitBtn.disabled = true;
            standBtn.disabled = true;
            newGameBtn.textContent = 'Deal Again';
        }
    }

    updateGameStatus(message, statusClass = '') {
        const statusElement = document.getElementById('gameStatus');
        statusElement.innerHTML = `<h4 class="mb-3 ${statusClass}">${message}</h4>`;
    }

    updateBalance() {
        // Update the blackjack page balance display
        const gameBalance = document.getElementById('userBalance');
        if (gameBalance) {
            gameBalance.textContent = this.userBalance.toLocaleString();
        }
        
        // Update ALL possible balance displays on the page
        const balanceSelectors = [
            '#userBalance',
            '.user-balance',
            '.balance-display span',
            '.navbar-balance',
            '[data-balance]'
        ];
        
        balanceSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (element && element.textContent !== this.userBalance.toLocaleString()) {
                    element.textContent = this.userBalance.toLocaleString();
                }
            });
        });
        
        // Update the navbar balance display
        this.updateNavbarBalance(this.userBalance);
    }
    
    updateNavbarBalance(newBalance) {
        // Update the navbar QR balance display (same as slot machine)
        const navbarBalance = document.getElementById('navbarQRBalance');
        if (navbarBalance) {
            navbarBalance.textContent = new Intl.NumberFormat().format(newBalance);
        } else {
        }
        
        // Also try updating other balance elements on the page
        const balanceElements = document.querySelectorAll('[id*="Balance"], [class*="balance"]');
        balanceElements.forEach(element => {
            if (element.id === 'userBalance' || element.classList.contains('user-balance')) {
                element.textContent = new Intl.NumberFormat().format(newBalance);
            }
        });
        
        // Dispatch custom event for other components to listen to
        window.dispatchEvent(new CustomEvent('balanceUpdated', {
            detail: { newBalance: newBalance }
        }));
        
        // Also dispatch the navbar-specific event
        window.dispatchEvent(new CustomEvent('balanceUpdate', {
            detail: { balance: newBalance }
        }));
    }

    updateBetDisplay() {
        document.getElementById('currentBet').textContent = `${this.currentBet} QR Coin${this.currentBet !== 1 ? 's' : ''}`;
    }

    addWinEffects() {
        // Add glow effect to winning cards
        document.querySelectorAll('.card-element').forEach(card => {
            card.classList.add('win-glow');
        });
        
        // Create celebration effect
        setTimeout(() => {
            document.querySelectorAll('.win-glow').forEach(card => {
                card.classList.remove('win-glow');
            });
        }, 3000);
    }

    async refreshBalanceFromServer() {
        try {
            
            // Use the existing balance API endpoint
            const response = await fetch(`${window.blackjackData.appUrl}/html/user/api/get-balance.php`, {
                method: 'GET',
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`Balance API responded with ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.balance !== undefined) {
                this.userBalance = data.balance;
                this.updateBalance();
                this.updateNavbarBalance(data.balance);
                
                // Trigger global balance update
                if (window.updateQRBalance) {
                    window.updateQRBalance();
                }
                
                if (window.triggerBalanceChange) {
                    window.triggerBalanceChange(data.balance);
                }
            } else {
                throw new Error(data.error || 'Invalid balance response');
            }
            
        } catch (error) {
            
            // Fallback: try other balance refresh methods
            if (window.qrBalanceManager) {
                window.qrBalanceManager.forceBalanceResync();
            } else if (window.updateQRBalance) {
                setTimeout(() => window.updateQRBalance(), 1000);
            }
        }
    }

    async recordGame(result, winAmount) {
        
        try {
            // CRITICAL FIX: Use correct API path and validate parameters
            // Ensure businessId is a valid integer before sending
            const businessIdInt = parseInt(this.businessId);
            if (isNaN(businessIdInt) || businessIdInt <= 0) {
                throw new Error('Invalid business configuration - please refresh the page');
            }
            
            const requestData = {
                bet_amount: parseInt(this.currentBet),
                win_amount: parseInt(winAmount),
                results: [{result: result}],
                business_id: businessIdInt,
                game_type: 'blackjack'
            };
            
            
            // Use the main casino API (now fixed to handle JSON properly)
            const apiUrl = `${window.blackjackData.appUrl}/html/api/casino/record-play.php`;
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(requestData)
            });
            
            
            // Get the response text first to see what we actually received
            const responseText = await response.text();
            
            if (!response.ok) {
                
                let errorData;
                try {
                    errorData = JSON.parse(responseText);
                } catch (jsonError) {
                    
                    if (response.status === 401) {
                        throw new Error('Authentication failed - please refresh and login again');
                    }
                    
                    throw new Error(`Server error (${response.status}): ${responseText.substring(0, 200)}`);
                }
                
                throw new Error(errorData.error || 'Failed to record blackjack game');
            }
            
            // Try to parse the success response
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error('Invalid response format');
            }
            
            
            if (data.success && data.new_balance !== undefined) {
                // Use server balance as source of truth
                this.userBalance = data.new_balance;
                this.updateBalance();
                this.updateNavbarBalance(data.new_balance);
                
                // FORCE update all balance displays on the page
                setTimeout(() => {
                    this.updateBalance();
                    this.updateNavbarBalance(data.new_balance);
                }, 100);
                
                // Handle resync request from server (silently)
                if (data.should_resync) {
                    if (window.qrBalanceManager) {
                        window.qrBalanceManager.forceBalanceResync();
                    }
                } else {
                    // Normal balance update
                    if (window.updateQRBalance) {
                        window.updateQRBalance();
                    }
                    
                    // Trigger balance change event
                    if (window.triggerBalanceChange) {
                        window.triggerBalanceChange(data.new_balance);
                    }
                }
                
            } else {
                throw new Error(data.error || 'Server response indicates failure');
            }
            
        } catch (error) {
            
            // Show user-friendly error message
            this.updateGameStatus(`⚠️ Connection issue - refreshing balance...`, 'status-warning');
            
            // Force a balance refresh from server immediately
            this.refreshBalanceFromServer();
            
            // Reset status after refresh
            setTimeout(() => {
                this.updateGameStatus('Place your bet and start playing!', '');
            }, 3000);
        }
    }
}

// Initialize the game when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.blackjackData !== 'undefined') {
        window.qrBlackjack = new QRBlackjack();
    } else {
    }
});