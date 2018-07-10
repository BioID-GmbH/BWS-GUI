using System.ComponentModel.DataAnnotations;

namespace uuisample.Models
{
    public class UuiFormModel
    {
        [Required]
        [DataType(DataType.Url)]
        public string ApiUrl { get; set; } = "https://bws.bioid.com/extension/";
        [Required]
        public string BCID { get; set; }
        [Required]
        public string Operation { get; set; }
        [Required]
        public bool ChallengeResponse { get; set; }
        [Required]
        public bool AutoEnroll { get; set; }
        [Required]
        public bool Face { get; set; }
        [Required]
        public bool Periocular { get; set; }
    }
}
