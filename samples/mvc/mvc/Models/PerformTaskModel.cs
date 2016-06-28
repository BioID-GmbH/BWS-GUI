using System.ComponentModel.DataAnnotations;

namespace mvc.Models
{
    public class PerformTaskModel
    {
        [Required]
        [DataType(DataType.Url)]
        [Display(Name = "BWS Extension host")]
        public string Host { get; set; } = "bws.bioid.com";
        [Required]
        [Display(Name = "Biometric Class ID")]
        public string BCID { get; set; }
        [Required]
        public bool ChallengeResponse { get; set; }
        [Required]
        public bool AutoEnroll { get; set; }
        public string Result { get; set; }
        public string Error { get; set; }
    }
}