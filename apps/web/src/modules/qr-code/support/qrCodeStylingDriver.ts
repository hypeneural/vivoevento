import QRCodeStyling, {
  type DownloadOptions,
  type ExtensionFunction,
  type FileExtension,
  type Options as QRCodeStylingOptions,
} from 'qr-code-styling';

export const QR_CODE_STYLING_VERSION = '1.9.2' as const;

export class QrCodeStylingDriver {
  private readonly instance: QRCodeStyling;

  constructor(options: QRCodeStylingOptions) {
    this.instance = new QRCodeStyling(options);
  }

  append(container: HTMLElement) {
    this.instance.append(container);
  }

  update(options: QRCodeStylingOptions) {
    this.instance.update(options);
  }

  download(options?: DownloadOptions) {
    return this.instance.download(options);
  }

  getRawData(extension?: FileExtension) {
    return this.instance.getRawData(extension);
  }

  applyExtension(extension: ExtensionFunction) {
    return this.instance.applyExtension(extension);
  }

  deleteExtension() {
    return this.instance.deleteExtension();
  }
}

export function createQrCodeStylingDriver(options: QRCodeStylingOptions) {
  return new QrCodeStylingDriver(options);
}

export type { QRCodeStylingOptions };
