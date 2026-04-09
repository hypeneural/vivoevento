import type { CommercialPackageCopy } from '../mappers/packageCommercialCopy';
import { PackageCard } from './PackageCard';

type PackageSelectionStepProps = {
  packages: CommercialPackageCopy[];
  selectedPackageId?: string | null;
  onSelect: (pkg: CommercialPackageCopy) => void;
};

export function PackageSelectionStep({
  packages,
  selectedPackageId,
  onSelect,
}: PackageSelectionStepProps) {
  return (
    <div className="grid gap-4 xl:grid-cols-2">
      {packages.map((pkg) => (
        <PackageCard
          key={pkg.id}
          pkg={pkg}
          selected={String(pkg.id) === selectedPackageId}
          onSelect={onSelect}
        />
      ))}
    </div>
  );
}
