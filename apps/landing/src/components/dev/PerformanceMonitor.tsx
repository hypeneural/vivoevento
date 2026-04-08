import { useState, useEffect } from 'react';
import styles from './PerformanceMonitor.module.scss';

type WebVitalData = {
  name: string;
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
  timestamp: number;
};

/**
 * Performance Monitor Component
 * 
 * Development-only component that displays real-time performance metrics
 * Shows Core Web Vitals and other performance indicators
 * 
 * Usage: Only render in development mode
 */
export default function PerformanceMonitor() {
  const [vitals, setVitals] = useState<WebVitalData[]>([]);
  const [isVisible, setIsVisible] = useState(false);
  const [fps, setFps] = useState(60);
  
  useEffect(() => {
    // Only run in development
    if (import.meta.env.MODE !== 'development') return;
    
    // Listen for Web Vitals
    const handleWebVital = (event: CustomEvent) => {
      const metric = event.detail;
      setVitals(prev => {
        const existing = prev.findIndex(v => v.name === metric.name);
        if (existing >= 0) {
          const updated = [...prev];
          updated[existing] = {
            name: metric.name,
            value: metric.value,
            rating: metric.rating,
            timestamp: Date.now(),
          };
          return updated;
        }
        return [...prev, {
          name: metric.name,
          value: metric.value,
          rating: metric.rating,
          timestamp: Date.now(),
        }];
      });
    };
    
    window.addEventListener('web-vital' as any, handleWebVital);
    
    // Monitor FPS
    let frameCount = 0;
    let lastTime = performance.now();
    
    const measureFPS = () => {
      frameCount++;
      const currentTime = performance.now();
      
      if (currentTime >= lastTime + 1000) {
        setFps(Math.round((frameCount * 1000) / (currentTime - lastTime)));
        frameCount = 0;
        lastTime = currentTime;
      }
      
      requestAnimationFrame(measureFPS);
    };
    
    requestAnimationFrame(measureFPS);
    
    return () => {
      window.removeEventListener('web-vital' as any, handleWebVital);
    };
  }, []);
  
  // Toggle visibility with keyboard shortcut (Ctrl+Shift+P)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.ctrlKey && e.shiftKey && e.key === 'P') {
        setIsVisible(prev => !prev);
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);
  
  if (!isVisible) {
    return (
      <button
        className={styles.toggle}
        onClick={() => setIsVisible(true)}
        title="Show Performance Monitor (Ctrl+Shift+P)"
      >
        📊
      </button>
    );
  }
  
  const getRatingColor = (rating: string) => {
    switch (rating) {
      case 'good': return '#0cce6b';
      case 'needs-improvement': return '#ffa400';
      case 'poor': return '#ff4e42';
      default: return '#888';
    }
  };
  
  const formatValue = (name: string, value: number) => {
    if (name === 'CLS') {
      return value.toFixed(3);
    }
    return `${Math.round(value)}ms`;
  };
  
  return (
    <div className={styles.monitor}>
      <div className={styles.header}>
        <h3>Performance Monitor</h3>
        <button
          className={styles.close}
          onClick={() => setIsVisible(false)}
          title="Hide (Ctrl+Shift+P)"
        >
          ×
        </button>
      </div>
      
      <div className={styles.metrics}>
        <div className={styles.metric}>
          <span className={styles.label}>FPS</span>
          <span 
            className={styles.value}
            style={{ color: fps >= 55 ? '#0cce6b' : fps >= 30 ? '#ffa400' : '#ff4e42' }}
          >
            {fps}
          </span>
        </div>
        
        {vitals.map(vital => (
          <div key={vital.name} className={styles.metric}>
            <span className={styles.label}>{vital.name}</span>
            <span 
              className={styles.value}
              style={{ color: getRatingColor(vital.rating) }}
            >
              {formatValue(vital.name, vital.value)}
            </span>
          </div>
        ))}
      </div>
      
      <div className={styles.legend}>
        <div className={styles.legendItem}>
          <span className={styles.dot} style={{ backgroundColor: '#0cce6b' }} />
          <span>Good</span>
        </div>
        <div className={styles.legendItem}>
          <span className={styles.dot} style={{ backgroundColor: '#ffa400' }} />
          <span>Needs Improvement</span>
        </div>
        <div className={styles.legendItem}>
          <span className={styles.dot} style={{ backgroundColor: '#ff4e42' }} />
          <span>Poor</span>
        </div>
      </div>
      
      <div className={styles.info}>
        <p>Press <kbd>Ctrl+Shift+P</kbd> to toggle</p>
        <p>Metrics update in real-time</p>
      </div>
    </div>
  );
}
