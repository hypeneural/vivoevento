import { useRive } from "@rive-app/react-canvas";

type RivePlayerProps = {
  src: string;
  stateMachines?: string | string[];
  artboard?: string;
  className?: string;
};

export default function RivePlayer({ src, stateMachines, artboard, className }: RivePlayerProps) {
  const { RiveComponent } = useRive({
    src,
    stateMachines,
    artboard,
    autoplay: true,
  });

  return <RiveComponent className={className} />;
}
