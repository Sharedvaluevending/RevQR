# System Optimization & Monitoring Plan

## 🚨 Immediate Fixes Implemented
- ✅ Killed runaway bash process (PID 61959) that was consuming 91% CPU
- ✅ System load reduced from 5.44 to 0.27

## 📊 Current System Analysis

### System Resources
- **RAM**: 1.9GB total, 1.2GB used (61% usage) - ⚠️ HIGH
- **Disk**: 52GB total, 13GB used (25% usage) - ✅ GOOD
- **Swap**: 143MB used out of 5.3GB - ⚠️ INDICATES MEMORY PRESSURE

### Active Services
- Apache2 (11 worker processes) - Normal
- MySQL - Running but credentials need setup
- Cursor IDE Server processes - Normal
- Various system services

## 🔧 Optimization Recommendations

### 1. Install System Monitoring Tools
```bash
# Install essential monitoring tools
apt update && apt install -y htop iotop nethogs glances

# Install process monitoring
apt install -y sysstat

# Enable process accounting
apt install -y acct
```

### 2. Memory Optimization

#### Apache Configuration
```bash
# Edit Apache configuration for better memory usage
nano /etc/apache2/mods-available/mpm_prefork.conf

# Recommended settings for 2GB RAM:
# StartServers: 2
# MinSpareServers: 2
# MaxSpareServers: 5
# MaxRequestWorkers: 20
# MaxConnectionsPerChild: 1000
```

#### PHP Optimization
```bash
# Check PHP memory settings
php -i | grep memory_limit

# Recommended PHP settings for web server:
# memory_limit = 128M (instead of 512M if higher)
# max_execution_time = 30
# max_input_vars = 3000
```

### 3. MySQL Optimization
```bash
# MySQL configuration tuning for 2GB RAM
# Add to /etc/mysql/mysql.conf.d/mysqld.cnf:

[mysqld]
innodb_buffer_pool_size = 512M
query_cache_size = 32M
query_cache_limit = 2M
max_connections = 50
thread_cache_size = 8
table_open_cache = 1024
```

### 4. System Monitoring Setup

#### Create CPU Monitor Script
```bash
#!/bin/bash
# /usr/local/bin/cpu_monitor.sh
THRESHOLD=80
HIGH_CPU_PROCESSES=$(ps aux --sort=-%cpu | awk 'NR>1 {if($3>'"$THRESHOLD"') print $2,$3,$11}')

if [ ! -z "$HIGH_CPU_PROCESSES" ]; then
    echo "$(date): High CPU usage detected:" >> /var/log/cpu_monitor.log
    echo "$HIGH_CPU_PROCESSES" >> /var/log/cpu_monitor.log
    
    # Send alert (optional)
    # mail -s "High CPU Alert" admin@domain.com < /var/log/cpu_monitor.log
fi
```

#### Add to Crontab
```bash
# Monitor CPU every 5 minutes
*/5 * * * * /usr/local/bin/cpu_monitor.sh

# Clean old logs weekly
0 0 * * 0 find /var/log -name "*.log" -mtime +7 -delete
```

### 5. Process Management

#### Systemd Service Limits
```bash
# Create limits for services
mkdir -p /etc/systemd/system/apache2.service.d/
echo -e "[Service]\nMemoryMax=500M\nCPUQuota=50%" > /etc/systemd/system/apache2.service.d/limits.conf

# Reload systemd
systemctl daemon-reload
systemctl restart apache2
```

### 6. Log Rotation & Cleanup
```bash
# Setup logrotate for application logs
cat > /etc/logrotate.d/webapps << EOF
/var/www/html/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    copytruncate
}
EOF
```

### 7. Swap Optimization
```bash
# Reduce swappiness to avoid excessive swap usage
echo "vm.swappiness=10" >> /etc/sysctl.conf
sysctl -p
```

## 🔍 Monitoring Commands

### Daily Health Checks
```bash
# System overview
htop

# Memory usage
free -h

# Disk usage
df -h

# Network connections
ss -tuln

# Top CPU processes
ps aux --sort=-%cpu | head -10

# MySQL processes (if accessible)
mysqladmin processlist

# Apache status
systemctl status apache2
```

### Weekly Performance Review
```bash
# System performance stats
sar -u 1 5  # CPU usage
sar -r 1 5  # Memory usage
sar -d 1 5  # Disk I/O

# Check for memory leaks
ps aux --sort=-%mem | head -10

# Review error logs
tail -100 /var/log/apache2/error.log
tail -100 /var/log/mysql/error.log
```

## 🚨 Warning Signs to Watch

1. **CPU Usage > 80%** for extended periods
2. **Memory Usage > 90%**
3. **Swap Usage > 500MB**
4. **Load Average > 2.0** consistently
5. **Disk Usage > 85%**
6. **Too many Apache processes** (>20)

## 🔄 Automated Alerts Setup

### Simple Bash Alert Script
```bash
#!/bin/bash
# /usr/local/bin/system_alert.sh

# Check CPU
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
    echo "HIGH CPU: $CPU_USAGE%" | logger -t SYSTEM_ALERT
fi

# Check Memory
MEM_USAGE=$(free | grep Mem | awk '{printf("%.1f", $3/$2 * 100.0)}')
if (( $(echo "$MEM_USAGE > 90" | bc -l) )); then
    echo "HIGH MEMORY: $MEM_USAGE%" | logger -t SYSTEM_ALERT
fi

# Check Load Average
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
if (( $(echo "$LOAD_AVG > 2.0" | bc -l) )); then
    echo "HIGH LOAD: $LOAD_AVG" | logger -t SYSTEM_ALERT
fi
```

## 📋 Implementation Priority

1. **HIGH PRIORITY**
   - Install monitoring tools
   - Setup CPU monitoring script
   - Configure Apache memory limits
   - Implement log rotation

2. **MEDIUM PRIORITY**
   - MySQL optimization
   - PHP memory tuning
   - Swap optimization
   - Automated alerts

3. **LOW PRIORITY**
   - Advanced monitoring setup
   - Performance baselines
   - Capacity planning

## 🎯 Expected Results

After implementing these optimizations:
- **Reduced memory pressure** (target <80% usage)
- **Better process management** (prevent runaway processes)
- **Improved response times**
- **Early warning system** for resource issues
- **Automated cleanup** of logs and temporary files
- **Stable system performance** under normal loads

## 📞 Emergency Response

If high CPU/memory occurs again:
1. Identify process: `ps aux --sort=-%cpu | head -5`
2. Check if it's legitimate: `ps -ef | grep [PID]`
3. Kill if necessary: `kill -15 [PID]` (then `kill -9` if needed)
4. Check logs: `tail -100 /var/log/syslog`
5. Monitor recovery: `watch 'uptime && free -h'` 