class PhpPie < Formula
  desc "PIE - the PHP Installer for Extensions"
  homepage "https://github.com/php/pie"
  url "https://github.com/php/pie/releases/download/0.2.0/pie.phar"
  sha256 "a88f2ad1939b69fe1b44919b22e68743b4de2f33ea560880010e8565eb21ab12"
  license "MIT"

  depends_on "php"

  def install
    bin.install "pie.phar" => "pie"
  end

  test do
    shell_output("#{bin}/pie --version").include?(version)
  end
end
