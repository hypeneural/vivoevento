import ReactDOM from "react-dom/client";
import { MotionConfig } from "motion/react";
import App from "./App.tsx";
import "./styles/globals.scss";
import { SmoothScroller } from "@/components/SmoothScroller";

ReactDOM.createRoot(document.getElementById("root")!).render(
  <MotionConfig reducedMotion="user" transition={{ duration: 0.28, ease: "easeOut" }}>
    <SmoothScroller>
      <App />
    </SmoothScroller>
  </MotionConfig>
);
